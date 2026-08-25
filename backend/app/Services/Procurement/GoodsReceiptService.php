<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\ProcurementStatusHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GoodsReceiptService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ProcurementWorkflow $workflow,
        private readonly ProcurementReferenceService $referenceService,
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly InventoryService $inventoryService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = GoodsReceipt::query()->with(['purchaseOrder', 'supplier', 'warehouse', 'receiver']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseOrder', fn (Builder $q) => $q->where('purchase_order_number', 'like', "%{$search}%"))
                    ->orWhereHas('supplier', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }
        foreach (['status', 'supplier_id', 'warehouse_id', 'purchase_order_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([
            'receipt_date_from' => ['receipt_date', '>='],
            'receipt_date_to' => ['receipt_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'receipt_number', 'receipt_date', 'status'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(GoodsReceipt $receipt): GoodsReceipt
    {
        return $receipt->load([
            'purchaseOrder.supplier',
            'supplier',
            'warehouse',
            'warehouseLocation',
            'receiver',
            'items.material',
            'items.unit',
            'items.purchaseOrderItem.purchaseOrder',
            'statusHistories.changer',
        ]);
    }

    public function create(array $attributes, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($attributes, $actor): GoodsReceipt {
            $order = PurchaseOrder::query()->with(['supplier', 'items'])->findOrFail($attributes['purchase_order_id']);
            $this->ensureReceivableOrder($order);
            $supplier = $this->referenceService->supplier((int) $attributes['supplier_id']);
            if ($supplier->getKey() !== $order->supplier_id) {
                throw ValidationException::withMessages(['supplier_id' => 'The Goods Receipt Supplier must match the Purchase Order Supplier.']);
            }
            $warehouse = $this->referenceService->warehouse((int) $attributes['warehouse_id']);
            $this->referenceService->warehouseLocation(isset($attributes['warehouse_location_id']) ? (int) $attributes['warehouse_location_id'] : null, $warehouse->getKey());
            $items = $this->resolveItems($order, $attributes['items'] ?? []);
            $receipt = GoodsReceipt::query()->create([
                'receipt_number' => $this->generateNumber(),
                'purchase_order_id' => $order->getKey(),
                'supplier_id' => $supplier->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'warehouse_location_id' => $attributes['warehouse_location_id'] ?? null,
                'receipt_date' => $attributes['receipt_date'],
                'received_by' => $actor->getKey(),
                'status' => ProcurementWorkflow::DRAFT,
                'remarks' => $attributes['remarks'] ?? null,
            ]);
            foreach ($items as $index => $item) {
                $created = $receipt->items()->create([
                    ...$item,
                    'line_number' => $index + 1,
                ]);
                $this->auditLogService->record($actor, 'procurement-goods-receipt-items', $created, 'created', null, $created->attributesToArray());
            }
            $this->recordStatus($receipt, null, ProcurementWorkflow::DRAFT, $actor, 'Goods Receipt created.');
            $this->auditLogService->record($actor, 'procurement-goods-receipts', $receipt, 'created', null, $receipt->attributesToArray());

            return $this->find($receipt->refresh());
        });
    }

    public function receive(GoodsReceipt $receipt, ?string $remarks, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $remarks, $actor): GoodsReceipt {
            $receipt->loadMissing('purchaseOrder.items', 'items');
            $this->ensureReceiptIntegrity($receipt);
            $this->transition($receipt, ProcurementWorkflow::RECEIVED, $remarks ?: 'Goods Receipt quantities received.', $actor);

            return $this->find($receipt->refresh());
        });
    }

    public function accept(GoodsReceipt $receipt, ?string $remarks, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $remarks, $actor): GoodsReceipt {
            if ($receipt->status !== ProcurementWorkflow::RECEIVED) {
                throw ValidationException::withMessages(['status' => 'Only received Goods Receipts can be inspected and accepted.']);
            }
            $receipt->loadMissing('items');
            $this->ensureReceiptQuantities($receipt->items->all());
            $this->transition($receipt, ProcurementWorkflow::ACCEPTED, $remarks ?: 'Goods Receipt inspected and accepted.', $actor);

            return $this->find($receipt->refresh());
        });
    }

    public function post(GoodsReceipt $receipt, ?string $remarks, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $remarks, $actor): GoodsReceipt {
            if ($receipt->status !== ProcurementWorkflow::ACCEPTED) {
                throw ValidationException::withMessages(['status' => 'Only accepted Goods Receipts can be posted.']);
            }
            $receipt->loadMissing('items.purchaseOrderItem', 'purchaseOrder.items');
            $this->ensureReceiptQuantities($receipt->items->all());
            foreach ($receipt->items as $item) {
                $poItem = PurchaseOrderItem::query()->lockForUpdate()->findOrFail($item->purchase_order_item_id);
                $poItem->increment('received_quantity', (float) $item->received_quantity);
                $this->auditLogService->record($actor, 'procurement-purchase-order-items', $poItem, 'receipt_posted', null, ['received_quantity' => $poItem->fresh()->received_quantity]);
            }
            // Inventory is posted before the receipt is marked complete so any stock failure rolls back the entire posting transaction.
            $this->inventoryService->postGoodsReceipt($receipt, $actor);
            $this->transition($receipt, ProcurementWorkflow::POSTED, $remarks ?: 'Goods Receipt posted and accepted quantities stocked in Inventory.', $actor);
            $receipt->forceFill(['posted_at' => now()])->save();
            $this->auditLogService->record($actor, 'procurement-goods-receipts', $receipt, 'posted', ['status' => ProcurementWorkflow::ACCEPTED], $receipt->attributesToArray());
            $this->purchaseOrderService->updateReceiptProgress($receipt->purchaseOrder()->lockForUpdate()->firstOrFail(), $actor);

            return $this->find($receipt->refresh());
        });
    }

    public function history(GoodsReceipt $receipt): array
    {
        return [
            'status_history' => $receipt->statusHistories()->with('changer')->get(),
            'audit_logs' => AuditLog::query()->where('module', 'procurement-goods-receipts')->where('record_id', $receipt->getKey())->latest('id')->get(),
        ];
    }

    private function ensureReceivableOrder(PurchaseOrder $order): void
    {
        if (! in_array($order->status, [ProcurementWorkflow::SENT_TO_SUPPLIER, ProcurementWorkflow::PARTIALLY_RECEIVED], true)) {
            throw ValidationException::withMessages(['purchase_order_id' => 'Goods Receipts require a Purchase Order sent to the Supplier or already partially received.']);
        }
    }

    private function resolveItems(PurchaseOrder $order, array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one Goods Receipt item is required.']);
        }
        $order->loadMissing('items.material', 'items.unit');
        $sourceIds = array_map(static fn (array $item): int => (int) ($item['purchase_order_item_id'] ?? 0), $items);
        if (count($sourceIds) !== count(array_unique($sourceIds))) {
            throw ValidationException::withMessages(['items' => 'Each Purchase Order item may appear only once on a Goods Receipt.']);
        }
        $resolved = [];
        foreach ($items as $index => $item) {
            $source = $order->items->firstWhere('id', (int) ($item['purchase_order_item_id'] ?? 0));
            if ($source === null) {
                throw ValidationException::withMessages(["items.{$index}.purchase_order_item_id" => 'The selected Purchase Order item does not belong to this Purchase Order.']);
            }
            $received = (float) ($item['received_quantity'] ?? 0);
            $accepted = (float) ($item['accepted_quantity'] ?? 0);
            $rejected = (float) ($item['rejected_quantity'] ?? 0);
            $remaining = max((float) $source->quantity - (float) $source->received_quantity, 0);
            if ($received <= 0 || $received > $remaining) {
                throw ValidationException::withMessages(["items.{$index}.received_quantity" => 'Received quantity must be positive and cannot exceed the remaining Purchase Order quantity.']);
            }
            if (round($received, 4) !== round($accepted + $rejected, 4)) {
                throw ValidationException::withMessages(["items.{$index}.accepted_quantity" => 'Received quantity must equal accepted quantity plus rejected quantity.']);
            }
            if ($accepted < 0 || $rejected < 0) {
                throw ValidationException::withMessages(["items.{$index}.accepted_quantity" => 'Accepted and rejected quantities cannot be negative.']);
            }
            $resolved[] = [
                'purchase_order_item_id' => $source->getKey(),
                'material_id' => $source->material_id,
                'unit_id' => $source->unit_id,
                'ordered_quantity' => $source->quantity,
                'received_quantity' => $received,
                'accepted_quantity' => $accepted,
                'rejected_quantity' => $rejected,
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        return $resolved;
    }

    private function ensureReceiptIntegrity(GoodsReceipt $receipt): void
    {
        if ($receipt->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'A Goods Receipt must contain at least one item.']);
        }
        $this->ensureReceiptQuantities($receipt->items->all());
    }

    /**
     * @param  array<int, GoodsReceiptItem>  $items
     */
    private function ensureReceiptQuantities(array $items): void
    {
        foreach ($items as $item) {
            if (round((float) $item->received_quantity, 4) !== round((float) $item->accepted_quantity + (float) $item->rejected_quantity, 4)) {
                throw ValidationException::withMessages(['items' => 'Every Goods Receipt item must satisfy received = accepted + rejected.']);
            }
        }
    }

    private function transition(GoodsReceipt $receipt, string $newStatus, string $remarks, User $actor): void
    {
        $oldStatus = $receipt->status;
        $this->workflow->assertReceiptTransition($receipt, $newStatus);
        $receipt->forceFill(['status' => $newStatus])->save();
        $receipt->statusHistories()->create([
            'document_type' => ProcurementStatusHistory::RECEIPT,
            'previous_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor->getKey(),
            'remarks' => $remarks,
        ]);
        $this->auditLogService->record($actor, 'procurement-goods-receipts', $receipt, 'status_changed', ['status' => $oldStatus], ['status' => $newStatus, 'remarks' => $remarks]);
    }

    private function recordStatus(GoodsReceipt $receipt, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        $receipt->statusHistories()->create([
            'document_type' => ProcurementStatusHistory::RECEIPT,
            'previous_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor->getKey(),
            'remarks' => $remarks,
        ]);
    }

    private function generateNumber(): string
    {
        $prefix = 'GRN-'.now()->format('Ymd');
        $sequence = GoodsReceipt::query()->where('receipt_number', 'like', "{$prefix}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $prefix, $sequence++);
        } while (GoodsReceipt::query()->where('receipt_number', $candidate)->exists());

        return $candidate;
    }
}
