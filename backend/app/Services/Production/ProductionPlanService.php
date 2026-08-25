<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\BuyerOrder;
use App\Models\Product;
use App\Models\ProductionPlan;
use App\Models\ProductVariant;
use App\Models\SupplyPlan;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use App\Services\Orders\BuyerOrderWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProductionPlanService
{
    public function __construct(
        private readonly ProductionWorkflow $workflow,
        private readonly AuditLogService $auditLogService,
        private readonly BuyerOrderWorkflow $buyerOrderWorkflow,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ProductionPlan::query()->with(['product', 'productVariant', 'supplyPlan', 'buyerOrder', 'creator']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('plan_number', 'like', "%{$search}%")
                    ->orWhere('priority', 'like', "%{$search}%")
                    ->orWhereHas('product', fn (Builder $q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('productVariant', fn (Builder $q) => $q->where('sku', 'like', "%{$search}%")->orWhere('variant_name', 'like', "%{$search}%"));
            });
        }
        foreach (['status', 'priority', 'product_id', 'product_variant_id', 'supply_plan_id', 'buyer_order_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([
            'planned_start_from' => ['planned_start_date', '>='],
            'planned_start_to' => ['planned_start_date', '<='],
            'planned_end_from' => ['planned_end_date', '>='],
            'planned_end_to' => ['planned_end_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'plan_number', 'planned_start_date', 'planned_end_date', 'planned_quantity', 'priority', 'status'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(ProductionPlan $plan): ProductionPlan
    {
        return $plan->load([
            'product',
            'productVariant.product',
            'supplyPlan',
            'buyerOrder',
            'creator',
            'productionOrders.items.material',
        ]);
    }

    public function create(array $attributes, User $actor): ProductionPlan
    {
        return DB::transaction(function () use ($attributes, $actor): ProductionPlan {
            $references = $this->validateReferences($attributes);
            $plan = ProductionPlan::query()->create([
                'plan_number' => $this->generateNumber('PP'),
                'product_id' => $references['product']->getKey(),
                'product_variant_id' => $references['variant']?->getKey(),
                'supply_plan_id' => $references['supply_plan']?->getKey(),
                'buyer_order_id' => $references['buyer_order']?->getKey(),
                'planned_quantity' => $attributes['planned_quantity'],
                'planned_start_date' => $attributes['planned_start_date'],
                'planned_end_date' => $attributes['planned_end_date'],
                'priority' => $attributes['priority'] ?? 'normal',
                'status' => ProductionWorkflow::DRAFT,
                'created_by' => $actor->getKey(),
                'remarks' => $attributes['remarks'] ?? null,
            ]);
            $this->auditLogService->record($actor, 'production-plans', $plan, 'created', null, $plan->attributesToArray());

            return $this->find($plan);
        });
    }

    public function update(ProductionPlan $plan, array $attributes, User $actor): ProductionPlan
    {
        return DB::transaction(function () use ($plan, $attributes, $actor): ProductionPlan {
            $locked = ProductionPlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            if ($locked->status !== ProductionWorkflow::DRAFT) {
                throw ValidationException::withMessages(['status' => 'Only draft Production Plans can be edited.']);
            }
            $references = $this->validateReferences($attributes);
            $old = $locked->attributesToArray();
            $locked->update([
                'product_id' => $references['product']->getKey(),
                'product_variant_id' => $references['variant']?->getKey(),
                'supply_plan_id' => $references['supply_plan']?->getKey(),
                'buyer_order_id' => $references['buyer_order']?->getKey(),
                'planned_quantity' => $attributes['planned_quantity'],
                'planned_start_date' => $attributes['planned_start_date'],
                'planned_end_date' => $attributes['planned_end_date'],
                'priority' => $attributes['priority'] ?? 'normal',
                'remarks' => $attributes['remarks'] ?? null,
            ]);
            $this->auditLogService->record($actor, 'production-plans', $locked, 'updated', $old, $locked->fresh()->attributesToArray());

            return $this->find($locked->fresh());
        });
    }

    public function approve(ProductionPlan $plan, User $actor): ProductionPlan
    {
        return $this->transition($plan, ProductionWorkflow::APPROVED, $actor);
    }

    public function transition(ProductionPlan $plan, string $status, User $actor): ProductionPlan
    {
        return DB::transaction(function () use ($plan, $status, $actor): ProductionPlan {
            $locked = ProductionPlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            if ($status === ProductionWorkflow::APPROVED && ! $actor->hasPermission('production.approve')) {
                throw ValidationException::withMessages(['status' => 'Production Plan approval requires production.approve.']);
            }
            $this->workflow->assertPlanTransition($locked, $status);
            $old = $locked->attributesToArray();
            $locked->update(['status' => $status]);
            $this->auditLogService->record($actor, 'production-plans', $locked, 'status_changed', $old, $locked->fresh()->attributesToArray());

            return $this->find($locked->fresh());
        });
    }

    /** @return array{product: Product, variant: ProductVariant|null, supply_plan: SupplyPlan|null, buyer_order: BuyerOrder|null} */
    private function validateReferences(array $attributes): array
    {
        $product = Product::query()->where('status', 'active')->find((int) ($attributes['product_id'] ?? 0));
        if ($product === null) {
            throw ValidationException::withMessages(['product_id' => 'The selected product must exist and be active.']);
        }
        $variant = null;
        if (($attributes['product_variant_id'] ?? null) !== null && $attributes['product_variant_id'] !== '') {
            $variant = ProductVariant::query()
                ->where('status', 'active')
                ->where('product_id', $product->getKey())
                ->find((int) $attributes['product_variant_id']);
            if ($variant === null) {
                throw ValidationException::withMessages(['product_variant_id' => 'The selected variant must belong to the active product.']);
            }
        }

        $supplyPlan = null;
        if (($attributes['supply_plan_id'] ?? null) !== null && $attributes['supply_plan_id'] !== '') {
            $supplyPlan = SupplyPlan::query()->find((int) $attributes['supply_plan_id']);
            if ($supplyPlan === null || (int) $supplyPlan->product_id !== $product->getKey() || (int) ($supplyPlan->product_variant_id ?? 0) !== (int) ($variant?->getKey() ?? 0)) {
                throw ValidationException::withMessages(['supply_plan_id' => 'The selected Supply Plan must match the product and variant.']);
            }
        }

        $buyerOrder = null;
        if (($attributes['buyer_order_id'] ?? null) !== null && $attributes['buyer_order_id'] !== '') {
            $buyerOrder = BuyerOrder::query()->find((int) $attributes['buyer_order_id']);
            if ($buyerOrder === null || ! in_array($buyerOrder->status, $this->buyerOrderWorkflow->firmDemandStatuses(), true)) {
                throw ValidationException::withMessages(['buyer_order_id' => 'The selected Buyer Order must be firm demand.']);
            }
            $itemQuery = $buyerOrder->items()->where('product_id', $product->getKey());
            if ($variant !== null) {
                $itemQuery->where('product_variant_id', $variant->getKey());
            }
            if (! $itemQuery->exists()) {
                throw ValidationException::withMessages(['buyer_order_id' => 'The Buyer Order does not contain the selected product and variant.']);
            }
        }

        if ($supplyPlan === null && $buyerOrder === null) {
            throw ValidationException::withMessages(['source' => 'Provide a Supply Plan or firm Buyer Order as the production source.']);
        }

        return [
            'product' => $product,
            'variant' => $variant,
            'supply_plan' => $supplyPlan,
            'buyer_order' => $buyerOrder,
        ];
    }

    private function generateNumber(string $prefix): string
    {
        $date = now()->format('Ymd');
        $base = $prefix.'-'.$date;
        $sequence = ProductionPlan::query()->where('plan_number', 'like', "{$base}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $base, $sequence++);
        } while (ProductionPlan::query()->where('plan_number', $candidate)->exists());

        return $candidate;
    }
}
