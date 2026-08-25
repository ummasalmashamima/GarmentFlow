<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\AuditLog;
use App\Models\ProcurementStatusHistory;
use App\Models\PurchaseApproval;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseOrderService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ProcurementWorkflow $workflow,
        private readonly ProcurementReferenceService $referenceService,
        private readonly PurchaseOrderCalculationService $calculationService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()->with(['supplier', 'creator']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('purchase_order_number', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }
        foreach (['status', 'supplier_id', 'currency'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([
            'po_date_from' => ['po_date', '>='],
            'po_date_to' => ['po_date', '<='],
            'expected_delivery_from' => ['expected_delivery_date', '>='],
            'expected_delivery_to' => ['expected_delivery_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'purchase_order_number', 'po_date', 'expected_delivery_date', 'total_amount', 'status'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(PurchaseOrder $order): PurchaseOrder
    {
        return $order->load([
            'supplier',
            'creator',
            'items.material',
            'items.unit',
            'items.purchaseRequisitionItem.requisition',
            'approvals.requester',
            'approvals.reviewer',
            'goodsReceipts.supplier',
            'goodsReceipts.warehouse',
            'goodsReceipts.items',
            'statusHistories.changer',
        ]);
    }

    public function preview(array $attributes): array
    {
        $items = array_map(static fn (array $item): array => [
            'quantity' => $item['quantity'] ?? 0,
            'unit_price' => $item['unit_price'] ?? 0,
            'material_id' => $item['material_id'] ?? null,
            'unit_id' => $item['unit_id'] ?? null,
        ], $attributes['items'] ?? []);
        $calculation = $this->calculationService->calculate($items, (float) ($attributes['tax_total'] ?? 0), (float) ($attributes['discount_total'] ?? 0));

        return [
            ...$calculation,
            'currency' => $attributes['currency'] ?? 'USD',
            'purchase_requisition_id' => $attributes['purchase_requisition_id'] ?? null,
        ];
    }

    public function createFromRequisition(PurchaseRequisition $requisition, array $attributes, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($requisition, $attributes, $actor): PurchaseOrder {
            if ($requisition->status !== ProcurementWorkflow::APPROVED) {
                throw ValidationException::withMessages(['purchase_requisition_id' => 'Only approved Purchase Requisitions can generate Purchase Orders.']);
            }
            $supplier = $this->referenceService->supplier((int) $attributes['supplier_id']);
            $requisition->loadMissing('items.material', 'items.unit');
            $items = $this->resolveRequisitionItems($requisition, $attributes['items'] ?? []);
            $calculation = $this->calculationService->calculate($items, (float) ($attributes['tax_total'] ?? 0), (float) ($attributes['discount_total'] ?? 0));
            $order = PurchaseOrder::query()->create([
                'purchase_order_number' => $this->generateNumber(),
                'supplier_id' => $supplier->getKey(),
                'po_date' => $attributes['po_date'],
                'expected_delivery_date' => $attributes['expected_delivery_date'],
                'currency' => $attributes['currency'] ?? 'USD',
                'payment_terms' => $attributes['payment_terms'] ?? null,
                'shipping_terms' => $attributes['shipping_terms'] ?? null,
                'subtotal' => $calculation['subtotal'],
                'tax_total' => $calculation['tax_total'],
                'discount_total' => $calculation['discount_total'],
                'total_amount' => $calculation['total_amount'],
                'status' => ProcurementWorkflow::DRAFT,
                'created_by' => $actor->getKey(),
                'remarks' => $attributes['remarks'] ?? null,
            ]);
            foreach ($calculation['items'] as $index => $item) {
                $created = $order->items()->create([
                    'purchase_requisition_item_id' => $item['purchase_requisition_item_id'],
                    'material_id' => $item['material_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'received_quantity' => 0,
                    'remarks' => $item['remarks'] ?? null,
                    'line_number' => $index + 1,
                ]);
                $this->auditLogService->record($actor, 'procurement-purchase-order-items', $created, 'created', null, $created->attributesToArray());
                $source = PurchaseRequisitionItem::query()->lockForUpdate()->findOrFail($item['purchase_requisition_item_id']);
                $source->increment('converted_quantity', (float) $item['quantity']);
            }
            $this->recordStatus($order, null, ProcurementWorkflow::DRAFT, $actor, 'Purchase Order created from approved Purchase Requisition.');
            $this->auditLogService->record($actor, 'procurement-purchase-orders', $order, 'created', null, $order->attributesToArray());
            $this->refreshRequisitionStatus($requisition, $actor);

            return $this->find($order->refresh());
        });
    }

    public function update(PurchaseOrder $order, array $attributes, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $attributes, $actor): PurchaseOrder {
            if ($order->status !== ProcurementWorkflow::DRAFT) {
                throw ValidationException::withMessages(['status' => 'Only draft Purchase Orders can be edited.']);
            }
            $oldValues = $order->attributesToArray();
            $items = $this->resolveExistingItems($order, $attributes['items'] ?? []);
            $calculation = $this->calculationService->calculate($items, (float) ($attributes['tax_total'] ?? 0), (float) ($attributes['discount_total'] ?? 0));
            $this->referenceService->supplier((int) $attributes['supplier_id']);
            $order->fill([
                'supplier_id' => $attributes['supplier_id'],
                'po_date' => $attributes['po_date'],
                'expected_delivery_date' => $attributes['expected_delivery_date'],
                'currency' => $attributes['currency'] ?? 'USD',
                'payment_terms' => $attributes['payment_terms'] ?? null,
                'shipping_terms' => $attributes['shipping_terms'] ?? null,
                'subtotal' => $calculation['subtotal'],
                'tax_total' => $calculation['tax_total'],
                'discount_total' => $calculation['discount_total'],
                'total_amount' => $calculation['total_amount'],
                'remarks' => $attributes['remarks'] ?? null,
            ])->save();
            $order->items()->delete();
            foreach ($calculation['items'] as $index => $item) {
                $created = $order->items()->create([
                    'purchase_requisition_item_id' => $item['purchase_requisition_item_id'] ?? null,
                    'material_id' => $item['material_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'received_quantity' => 0,
                    'remarks' => $item['remarks'] ?? null,
                    'line_number' => $index + 1,
                ]);
                $this->auditLogService->record($actor, 'procurement-purchase-order-items', $created, 'created', null, $created->attributesToArray());
            }
            $this->auditLogService->record($actor, 'procurement-purchase-orders', $order, 'updated', $oldValues, $order->attributesToArray());

            return $this->find($order->refresh());
        });
    }

    public function submit(PurchaseOrder $order, ?string $remarks, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): PurchaseOrder {
            $this->ensureOrderIntegrity($order);
            $this->transition($order, ProcurementWorkflow::SUBMITTED, $remarks ?: 'Purchase Order submitted for approval.', $actor);
            $approval = $order->approvals()->create([
                'document_type' => PurchaseApproval::ORDER,
                'requested_by' => $actor->getKey(),
                'status' => 'pending',
                'remarks' => $remarks,
                'requested_at' => now(),
            ]);
            $this->auditLogService->record($actor, 'procurement-approvals', $approval, 'requested', null, $approval->attributesToArray());

            return $this->find($order->refresh());
        });
    }

    public function approve(PurchaseOrder $order, ?string $remarks, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): PurchaseOrder {
            if ($order->status !== ProcurementWorkflow::SUBMITTED) {
                throw ValidationException::withMessages(['status' => 'Only submitted Purchase Orders can be approved.']);
            }
            $approval = $order->approvals()->where('status', 'pending')->latest('id')->first();
            if ($approval === null) {
                throw ValidationException::withMessages(['approval' => 'A pending Purchase Order approval is required.']);
            }
            $oldApproval = $approval->attributesToArray();
            $approval->forceFill(['status' => 'approved', 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'remarks' => $remarks ?? $approval->remarks])->save();
            $this->auditLogService->record($actor, 'procurement-approvals', $approval, 'approved', $oldApproval, $approval->attributesToArray());
            $this->transition($order, ProcurementWorkflow::APPROVED, $remarks ?: 'Purchase Order approved.', $actor);

            return $this->find($order->refresh());
        });
    }

    public function sendToSupplier(PurchaseOrder $order, ?string $remarks, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): PurchaseOrder {
            $this->transition($order, ProcurementWorkflow::SENT_TO_SUPPLIER, $remarks ?: 'Purchase Order sent to Supplier.', $actor);

            return $this->find($order->refresh());
        });
    }

    public function cancel(PurchaseOrder $order, ?string $remarks, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): PurchaseOrder {
            $this->transition($order, ProcurementWorkflow::CANCELLED, $remarks ?: 'Purchase Order cancelled.', $actor);

            return $this->find($order->refresh());
        });
    }

    public function close(PurchaseOrder $order, ?string $remarks, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $remarks, $actor): PurchaseOrder {
            if (! $this->isFullyReceived($order)) {
                throw ValidationException::withMessages(['status' => 'Only fully received Purchase Orders can be closed.']);
            }
            $this->transition($order, ProcurementWorkflow::CLOSED, $remarks ?: 'Purchase Order closed.', $actor);

            return $this->find($order->refresh());
        });
    }

    public function updateReceiptProgress(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        $order->loadMissing('items');
        if ($order->status === ProcurementWorkflow::CANCELLED || $order->status === ProcurementWorkflow::CLOSED) {
            return $order;
        }
        $hasReceived = $order->items->sum(fn (PurchaseOrderItem $item): float => (float) $item->received_quantity) > 0;
        if (! $hasReceived) {
            return $order;
        }
        $newStatus = $this->isFullyReceived($order)
            ? ProcurementWorkflow::FULLY_RECEIVED
            : ProcurementWorkflow::PARTIALLY_RECEIVED;
        if ($order->status === $newStatus) {
            return $order;
        }
        $this->transition($order, $newStatus, $newStatus === ProcurementWorkflow::FULLY_RECEIVED ? 'All Purchase Order quantities received.' : 'Purchase Order partially received.', $actor);

        return $order->refresh();
    }

    public function history(PurchaseOrder $order): array
    {
        return [
            'status_history' => $order->statusHistories()->with('changer')->get(),
            'approvals' => $order->approvals()->with(['requester', 'reviewer'])->latest('id')->get(),
            'audit_logs' => AuditLog::query()->where('module', 'procurement-purchase-orders')->where('record_id', $order->getKey())->latest('id')->get(),
        ];
    }

    public function isFullyReceived(PurchaseOrder $order): bool
    {
        $order->loadMissing('items');

        return $order->items->isNotEmpty() && $order->items->every(fn (PurchaseOrderItem $item): bool => (float) $item->received_quantity >= (float) $item->quantity);
    }

    private function ensureOrderIntegrity(PurchaseOrder $order): void
    {
        $order->loadMissing('items');
        if ($order->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'A Purchase Order must contain at least one item.']);
        }
        if ($order->expected_delivery_date->lt($order->po_date)) {
            throw ValidationException::withMessages(['expected_delivery_date' => 'The expected delivery date must be on or after the PO date.']);
        }
    }

    private function resolveRequisitionItems(PurchaseRequisition $requisition, array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one Purchase Order item is required.']);
        }
        $sourceIds = array_map(static fn (array $item): int => (int) ($item['purchase_requisition_item_id'] ?? 0), $items);
        if (count($sourceIds) !== count(array_unique($sourceIds))) {
            throw ValidationException::withMessages(['items' => 'Each Purchase Requisition item may appear only once in a Purchase Order.']);
        }
        $resolved = [];
        foreach ($items as $index => $item) {
            $source = $requisition->items->firstWhere('id', (int) ($item['purchase_requisition_item_id'] ?? 0));
            if ($source === null) {
                throw ValidationException::withMessages(["items.{$index}.purchase_requisition_item_id" => 'The source item does not belong to this approved Purchase Requisition.']);
            }
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity <= 0 || $quantity > $source->remainingQuantity()) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => 'The PO quantity must be positive and no greater than the source item remaining quantity.']);
            }
            $resolved[] = [
                'purchase_requisition_item_id' => $source->getKey(),
                'material_id' => $source->material_id,
                'unit_id' => $source->unit_id,
                'quantity' => $quantity,
                'unit_price' => max((float) ($item['unit_price'] ?? 0), 0),
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        return $resolved;
    }

    private function resolveExistingItems(PurchaseOrder $order, array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one Purchase Order item is required.']);
        }
        $order->loadMissing('items.purchaseRequisitionItem');

        return array_map(function (array $item): array {
            $source = isset($item['purchase_requisition_item_id'])
                ? PurchaseRequisitionItem::query()->find($item['purchase_requisition_item_id'])
                : null;
            if ($source === null) {
                throw ValidationException::withMessages(['items' => 'Draft PO updates require source Purchase Requisition items.']);
            }
            $this->referenceService->material((int) $source->material_id);
            $this->referenceService->unit((int) $source->unit_id);
            if ((float) ($item['quantity'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['items' => 'Each PO quantity must be greater than zero.']);
            }

            return [
                'purchase_requisition_item_id' => $source->getKey(),
                'material_id' => $source->material_id,
                'unit_id' => $source->unit_id,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] ?? 0,
                'remarks' => $item['remarks'] ?? null,
            ];
        }, $items);
    }

    private function transition(PurchaseOrder $order, string $newStatus, string $remarks, User $actor): void
    {
        $oldStatus = $order->status;
        $this->workflow->assertOrderTransition($order, $newStatus);
        $order->forceFill(['status' => $newStatus])->save();
        $order->statusHistories()->create([
            'document_type' => ProcurementStatusHistory::ORDER,
            'previous_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor->getKey(),
            'remarks' => $remarks,
        ]);
        $this->auditLogService->record($actor, 'procurement-purchase-orders', $order, 'status_changed', ['status' => $oldStatus], ['status' => $newStatus, 'remarks' => $remarks]);
    }

    private function recordStatus(PurchaseOrder $order, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        $order->statusHistories()->create([
            'document_type' => ProcurementStatusHistory::ORDER,
            'previous_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor->getKey(),
            'remarks' => $remarks,
        ]);
    }

    private function refreshRequisitionStatus(PurchaseRequisition $requisition, User $actor): void
    {
        $requisition->load('items');
        $fullyConverted = $requisition->items->isNotEmpty() && $requisition->items->every(fn (PurchaseRequisitionItem $item): bool => (float) $item->converted_quantity >= (float) $item->quantity);
        if ($fullyConverted) {
            $oldStatus = $requisition->status;
            $this->workflow->assertRequisitionTransition($requisition, ProcurementWorkflow::CONVERTED_TO_PO);
            $requisition->forceFill(['status' => ProcurementWorkflow::CONVERTED_TO_PO])->save();
            $requisition->statusHistories()->create([
                'document_type' => ProcurementStatusHistory::REQUISITION,
                'previous_status' => $oldStatus,
                'new_status' => ProcurementWorkflow::CONVERTED_TO_PO,
                'changed_by' => $actor->getKey(),
                'remarks' => 'All approved Purchase Requisition quantities converted to Purchase Order.',
            ]);
            $this->auditLogService->record($actor, 'procurement-requisitions', $requisition, 'converted_to_po', ['status' => $oldStatus], ['status' => $requisition->status]);
        }
    }

    private function generateNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ymd');
        $sequence = PurchaseOrder::withTrashed()->where('purchase_order_number', 'like', "{$prefix}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $prefix, $sequence++);
        } while (PurchaseOrder::withTrashed()->where('purchase_order_number', $candidate)->exists());

        return $candidate;
    }
}
