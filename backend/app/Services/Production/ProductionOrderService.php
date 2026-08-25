<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\BomHeader;
use App\Models\FinishedGoods;
use App\Models\InventoryBalance;
use App\Models\ProductionOrder;
use App\Models\ProductionPlan;
use App\Models\User;
use App\Services\BOM\BOMCalculationService;
use App\Services\Inventory\InventoryReferenceService;
use App\Services\Inventory\InventoryService;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProductionOrderService
{
    public function __construct(
        private readonly ProductionWorkflow $workflow,
        private readonly BOMCalculationService $bomCalculationService,
        private readonly InventoryReferenceService $inventoryReferenceService,
        private readonly InventoryService $inventoryService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ProductionOrder::query()->with(['productionPlan', 'product', 'productVariant', 'bomVersion', 'issueWarehouse', 'issueWarehouseLocation', 'creator']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('order_number', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('product', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('productVariant', fn (Builder $q) => $q->where('sku', 'like', "%{$search}%")->orWhere('variant_name', 'like', "%{$search}%"));
            });
        }
        foreach (['status', 'product_id', 'product_variant_id', 'production_plan_id', 'buyer_order_id', 'issue_warehouse_id', 'issue_warehouse_location_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([
            'expected_completion_from' => ['expected_completion_date', '>='],
            'expected_completion_to' => ['expected_completion_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'order_number', 'planned_quantity', 'completed_quantity', 'status', 'expected_completion_date', 'created_at'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(ProductionOrder $order): ProductionOrder
    {
        return $order->load([
            'productionPlan',
            'buyerOrder',
            'product',
            'productVariant.product',
            'bomVersion.bomHeader',
            'issueWarehouse',
            'issueWarehouseLocation',
            'creator',
            'completer',
            'items.material',
            'items.unit',
            'progress.recorder',
            'consumptions.material',
            'consumptions.unit',
            'finishedGoods.inventoryTransaction',
        ]);
    }

    public function create(array $attributes, User $actor): ProductionOrder
    {
        return DB::transaction(function () use ($attributes, $actor): ProductionOrder {
            $plan = ProductionPlan::query()->lockForUpdate()->find((int) ($attributes['production_plan_id'] ?? 0));
            if ($plan === null || ! in_array($plan->status, [ProductionWorkflow::APPROVED, ProductionWorkflow::SCHEDULED], true)) {
                throw ValidationException::withMessages(['production_plan_id' => 'Production Orders require an approved or scheduled Production Plan.']);
            }
            if (ProductionOrder::query()->where('production_plan_id', $plan->getKey())->exists()) {
                throw ValidationException::withMessages(['production_plan_id' => 'This Production Plan already has a Production Order.']);
            }
            $plan->loadMissing(['product', 'productVariant']);
            $warehouse = $this->inventoryReferenceService->warehouse((int) ($attributes['issue_warehouse_id'] ?? 0));
            $location = $this->inventoryReferenceService->location(
                ($attributes['issue_warehouse_location_id'] ?? null) === null || ($attributes['issue_warehouse_location_id'] ?? '') === ''
                    ? null : (int) $attributes['issue_warehouse_location_id'],
                $warehouse->getKey(),
            );
            $quantity = (float) ($attributes['planned_quantity'] ?? $plan->planned_quantity);
            if ($quantity <= 0) {
                throw ValidationException::withMessages(['planned_quantity' => 'Production quantity must be greater than zero.']);
            }
            if ($quantity > (float) $plan->planned_quantity && ! $actor->hasPermission('production.override')) {
                throw ValidationException::withMessages(['planned_quantity' => 'Production quantity cannot exceed the Production Plan without production.override.']);
            }

            $bom = BomHeader::query()
                ->where('product_id', $plan->product_id)
                ->where('status', 'active')
                ->with('activeVersion')
                ->first();
            if ($bom === null || $bom->activeVersion === null) {
                throw ValidationException::withMessages(['production_plan_id' => 'No active BOM version exists for the Production Plan product.']);
            }
            $calculation = $this->bomCalculationService->calculate($bom->activeVersion, $quantity);
            $order = ProductionOrder::query()->create([
                'order_number' => $this->generateNumber(),
                'production_plan_id' => $plan->getKey(),
                'buyer_order_id' => $plan->buyer_order_id,
                'product_id' => $plan->product_id,
                'product_variant_id' => $plan->product_variant_id,
                'bom_version_id' => $bom->activeVersion->getKey(),
                'planned_quantity' => $quantity,
                'completed_quantity' => 0,
                'rejected_quantity' => 0,
                'start_date' => null,
                'expected_completion_date' => $attributes['expected_completion_date'] ?? $plan->planned_end_date,
                'completed_date' => null,
                'issue_warehouse_id' => $warehouse->getKey(),
                'issue_warehouse_location_id' => $location?->getKey(),
                'status' => ProductionWorkflow::SCHEDULED,
                'created_by' => $actor->getKey(),
                'remarks' => $attributes['remarks'] ?? null,
            ]);
            foreach ($calculation['lines'] as $line) {
                $order->items()->create([
                    'bom_item_id' => $line['item_id'],
                    'material_id' => $line['material']['id'],
                    'unit_id' => $line['unit']['id'],
                    'bom_quantity' => $line['bom_quantity'],
                    'wastage_percentage' => $line['wastage_percentage'],
                    'required_quantity' => $line['required_quantity'],
                    'consumed_quantity' => 0,
                    'remarks' => null,
                ]);
            }
            $this->auditLogService->record($actor, 'production-orders', $order, 'created', null, $order->attributesToArray());
            if ($plan->status === ProductionWorkflow::APPROVED) {
                $oldPlan = $plan->attributesToArray();
                $plan->update(['status' => ProductionWorkflow::SCHEDULED]);
                $this->auditLogService->record($actor, 'production-plans', $plan, 'status_changed', $oldPlan, $plan->fresh()->attributesToArray());
            }

            return $this->find($order->fresh());
        });
    }

    /** @return array{production_order: array<string, mixed>, available: bool, lines: array<int, array<string, mixed>>} */
    public function availability(ProductionOrder $order): array
    {
        $order->loadMissing(['items.material', 'items.unit', 'product', 'productVariant', 'issueWarehouse', 'issueWarehouseLocation']);
        $lines = $order->items->map(function ($item) use ($order): array {
            $identity = [
                'item_type' => 'material',
                'material_id' => $item->material_id,
                'product_id' => null,
                'product_variant_id' => null,
                'unit_id' => $item->unit_id,
            ];
            $stockKey = $this->inventoryReferenceService->stockKey($identity, $order->issue_warehouse_id, $order->issue_warehouse_location_id);
            $balance = InventoryBalance::query()->where('stock_key', $stockKey)->first();
            $required = (float) $item->required_quantity;
            $consumed = (float) $item->consumed_quantity;
            $remaining = max($required - $consumed, 0);
            $available = (float) ($balance?->available_quantity ?? 0);
            $shortage = max($remaining - $available, 0);

            return [
                'production_order_item_id' => $item->getKey(),
                'material' => [
                    'id' => $item->material->getKey(),
                    'code' => $item->material->code,
                    'name' => $item->material->name,
                ],
                'unit' => [
                    'id' => $item->unit->getKey(),
                    'code' => $item->unit->code,
                    'name' => $item->unit->name,
                    'symbol' => $item->unit->symbol,
                ],
                'required_quantity' => round($required, 4),
                'consumed_quantity' => round($consumed, 4),
                'remaining_quantity' => round($remaining, 4),
                'available_quantity' => round($available, 4),
                'shortage_quantity' => round($shortage, 4),
                'stock_key' => $stockKey,
            ];
        })->values()->all();

        return [
            'production_order' => [
                'id' => $order->getKey(),
                'order_number' => $order->order_number,
                'status' => $order->status,
                'issue_warehouse_id' => $order->issue_warehouse_id,
                'issue_warehouse_location_id' => $order->issue_warehouse_location_id,
            ],
            'available' => count(array_filter($lines, static fn (array $line): bool => $line['shortage_quantity'] > 0)) === 0,
            'lines' => $lines,
        ];
    }

    public function start(ProductionOrder $order, User $actor): ProductionOrder
    {
        return DB::transaction(function () use ($order, $actor): ProductionOrder {
            $locked = ProductionOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->workflow->assertOrderTransition($locked, ProductionWorkflow::IN_PROGRESS);
            $availability = $this->availability($locked);
            if (! $availability['available'] && ! $actor->hasPermission('production.override')) {
                throw ValidationException::withMessages(['availability' => 'Production cannot start while required materials are short.']);
            }
            $old = $locked->attributesToArray();
            $locked->update(['status' => ProductionWorkflow::IN_PROGRESS, 'start_date' => $locked->start_date ?? now()->toDateString()]);
            $this->auditLogService->record($actor, 'production-orders', $locked, 'status_changed', $old, $locked->fresh()->attributesToArray());
            $plan = ProductionPlan::query()->lockForUpdate()->find($locked->production_plan_id);
            if ($plan !== null && $plan->status === ProductionWorkflow::SCHEDULED) {
                $oldPlan = $plan->attributesToArray();
                $plan->update(['status' => ProductionWorkflow::IN_PROGRESS]);
                $this->auditLogService->record($actor, 'production-plans', $plan, 'status_changed', $oldPlan, $plan->fresh()->attributesToArray());
            }

            return $this->find($locked->fresh());
        });
    }

    public function complete(ProductionOrder $order, array $attributes, User $actor): ProductionOrder
    {
        return DB::transaction(function () use ($order, $attributes, $actor): ProductionOrder {
            $locked = ProductionOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($locked->status !== ProductionWorkflow::IN_PROGRESS) {
                throw ValidationException::withMessages(['status' => 'Only Production Orders in progress can be completed.']);
            }
            $locked->loadMissing(['product', 'productVariant.product', 'issueWarehouse', 'issueWarehouseLocation']);
            $finishedQuantity = (float) ($attributes['finished_quantity'] ?? 0);
            if ($finishedQuantity <= 0) {
                throw ValidationException::withMessages(['finished_quantity' => 'Finished quantity must be greater than zero.']);
            }
            $completedQuantity = (float) ($attributes['completed_quantity'] ?? max((float) $locked->completed_quantity, $finishedQuantity));
            $rejectedQuantity = (float) ($attributes['rejected_quantity'] ?? $locked->rejected_quantity);
            if (($completedQuantity > (float) $locked->planned_quantity || $finishedQuantity > (float) $locked->planned_quantity) && ! $actor->hasPermission('production.override')) {
                throw ValidationException::withMessages(['finished_quantity' => 'Completed production cannot exceed the planned quantity without production.override.']);
            }
            if ($completedQuantity < (float) $locked->completed_quantity) {
                throw ValidationException::withMessages(['completed_quantity' => 'Completed quantity cannot decrease.']);
            }
            if ($completedQuantity + $rejectedQuantity < (float) $locked->planned_quantity && ! $actor->hasPermission('production.override')) {
                throw ValidationException::withMessages(['finished_quantity' => 'Completion must account for the full planned quantity as completed or rejected.']);
            }

            $finishedGoods = FinishedGoods::query()->create([
                'finished_goods_number' => $this->generateFinishedGoodsNumber(),
                'production_order_id' => $locked->getKey(),
                'product_id' => $locked->product_id,
                'product_variant_id' => $locked->product_variant_id,
                'unit_id' => $locked->product->unit_id,
                'quantity' => $finishedQuantity,
                'warehouse_id' => $locked->issue_warehouse_id,
                'warehouse_location_id' => $locked->issue_warehouse_location_id,
                'inventory_transaction_id' => null,
                'idempotency_key' => 'production-order:'.$locked->getKey().':finished-goods',
                'finished_date' => $attributes['finished_date'] ?? now()->toDateString(),
                'recorded_by' => $actor->getKey(),
                'remarks' => $attributes['remarks'] ?? null,
            ]);
            $itemAttributes = $locked->product_variant_id !== null
                ? ['product_variant_id' => $locked->product_variant_id, 'product_id' => $locked->product_id, 'material_id' => null]
                : ['product_id' => $locked->product_id, 'material_id' => null, 'product_variant_id' => null];
            $movement = $this->inventoryService->stockIn([
                ...$itemAttributes,
                'unit_id' => $locked->product->unit_id,
                'warehouse_id' => $locked->issue_warehouse_id,
                'warehouse_location_id' => $locked->issue_warehouse_location_id,
                'quantity' => $finishedQuantity,
                'transaction_date' => $finishedGoods->finished_date->toDateString(),
                'reference_type' => FinishedGoods::class,
                'reference_id' => $finishedGoods->getKey(),
                'idempotency_key' => 'production-order:'.$locked->getKey().':finished-goods',
                'remarks' => 'Finished goods from Production Order '.$locked->order_number.'.',
            ], $actor);
            $finishedGoods->update(['inventory_transaction_id' => $movement['transaction']->getKey()]);
            $progressPercentage = $locked->planned_quantity > 0 ? ($completedQuantity / (float) $locked->planned_quantity) * 100 : 0;
            $locked->progress()->create([
                'planned_quantity' => $locked->planned_quantity,
                'completed_quantity' => $completedQuantity,
                'rejected_quantity' => $rejectedQuantity,
                'remaining_quantity' => max((float) $locked->planned_quantity - $completedQuantity, 0),
                'progress_percentage' => $progressPercentage,
                'production_date' => $attributes['finished_date'] ?? now()->toDateString(),
                'recorded_by' => $actor->getKey(),
                'remarks' => 'Completion recorded with finished goods output.',
            ]);
            $old = $locked->attributesToArray();
            $locked->update([
                'completed_quantity' => $completedQuantity,
                'rejected_quantity' => $rejectedQuantity,
                'status' => ProductionWorkflow::COMPLETED,
                'completed_date' => $attributes['finished_date'] ?? now()->toDateString(),
                'completed_by' => $actor->getKey(),
            ]);
            $this->auditLogService->record($actor, 'finished-goods', $finishedGoods, 'posted', null, $finishedGoods->fresh()->attributesToArray());
            $this->auditLogService->record($actor, 'production-orders', $locked, 'status_changed', $old, $locked->fresh()->attributesToArray());
            $plan = ProductionPlan::query()->lockForUpdate()->find($locked->production_plan_id);
            if ($plan !== null && $plan->status === ProductionWorkflow::IN_PROGRESS) {
                $oldPlan = $plan->attributesToArray();
                $plan->update(['status' => ProductionWorkflow::COMPLETED]);
                $this->auditLogService->record($actor, 'production-plans', $plan, 'status_changed', $oldPlan, $plan->fresh()->attributesToArray());
            }

            return $this->find($locked->fresh());
        });
    }

    public function transition(ProductionOrder $order, string $status, User $actor): ProductionOrder
    {
        if ($status === ProductionWorkflow::IN_PROGRESS) {
            return $this->start($order, $actor);
        }

        return DB::transaction(function () use ($order, $status, $actor): ProductionOrder {
            $locked = ProductionOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->workflow->assertOrderTransition($locked, $status);
            $old = $locked->attributesToArray();
            $locked->update(['status' => $status]);
            $this->auditLogService->record($actor, 'production-orders', $locked, 'status_changed', $old, $locked->fresh()->attributesToArray());

            return $this->find($locked->fresh());
        });
    }

    private function generateNumber(): string
    {
        $base = 'PROD-'.now()->format('Ymd');
        $sequence = ProductionOrder::query()->where('order_number', 'like', "{$base}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $base, $sequence++);
        } while (ProductionOrder::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }

    private function generateFinishedGoodsNumber(): string
    {
        $base = 'FG-'.now()->format('Ymd');
        $sequence = FinishedGoods::query()->where('finished_goods_number', 'like', "{$base}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $base, $sequence++);
        } while (FinishedGoods::query()->where('finished_goods_number', $candidate)->exists());

        return $candidate;
    }
}
