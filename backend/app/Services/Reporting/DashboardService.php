<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Delivery;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SupplyPlan;
use App\Services\Finance\AccountsReceivableService;
use App\Services\Finance\ProfitSummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DashboardService
{
    private const DASHBOARDS = [
        'executive' => ['label' => 'Executive dashboard', 'permission' => 'dashboard.executive.view'],
        'supply-chain' => ['label' => 'Supply Chain dashboard', 'permission' => 'dashboard.supply-chain.view'],
        'production' => ['label' => 'Production dashboard', 'permission' => 'dashboard.production.view'],
        'procurement' => ['label' => 'Procurement dashboard', 'permission' => 'dashboard.procurement.view'],
        'warehouse' => ['label' => 'Warehouse dashboard', 'permission' => 'dashboard.warehouse.view'],
    ];

    public function __construct(
        private readonly AccountsReceivableService $receivables,
        private readonly ProfitSummaryService $profit,
    ) {}

    public function supported(): array
    {
        return self::DASHBOARDS;
    }

    /** @return array<string, mixed> */
    public function show(string $key, array $filters): array
    {
        abort_unless(isset(self::DASHBOARDS[$key]), 404, 'Unsupported dashboard.');
        $filters = $this->normalizeFilters($filters);
        $data = match ($key) {
            'executive' => $this->executive($filters),
            'supply-chain' => $this->supplyChain($filters),
            'production' => $this->production($filters),
            'procurement' => $this->procurement($filters),
            'warehouse' => $this->warehouse($filters),
        };

        return ['dashboard' => $key, 'label' => self::DASHBOARDS[$key]['label'], 'filters' => $filters, 'generated_at' => now()->toISOString(), ...$data];
    }

    /** @return array<string, mixed> */
    private function executive(array $filters): array
    {
        $sales = $this->salesQuery($filters);
        $delivery = $this->deliveryQuery($filters);
        $production = $this->productionQuery($filters);
        $ar = $this->receivables->summary($this->financeFilters($filters));
        $profit = $this->profit->summary($this->financeFilters($filters));

        return [
            'kpis' => [
                ['key' => 'sales_orders', 'label' => 'Sales orders', 'value' => (clone $sales)->count(), 'format' => 'number'],
                ['key' => 'sales_value', 'label' => 'Sales value', 'value' => (float) (clone $sales)->sum('total_amount'), 'format' => 'amount'],
                ['key' => 'outstanding_receivables', 'label' => 'Outstanding receivables', 'value' => (float) ($ar['outstanding_amount'] ?? 0), 'format' => 'amount'],
                ['key' => 'gross_profit', 'label' => 'Gross profit', 'value' => $profit['gross_profit'] ?? null, 'format' => 'amount', 'complete' => (bool) ($profit['cost_data_complete'] ?? false)],
                ['key' => 'open_deliveries', 'label' => 'Open deliveries', 'value' => (clone $delivery)->whereNotIn('status', ['delivered', 'completed', 'cancelled'])->count(), 'format' => 'number'],
                ['key' => 'production_in_progress', 'label' => 'Production in progress', 'value' => (clone $production)->where('status', 'in_progress')->count(), 'format' => 'number'],
            ],
            'series' => [
                'sales_by_date' => $this->dateSeries($sales, 'order_date', 'total_amount', 'sales_value'),
                'orders_by_status' => $this->statusSeries($sales),
            ],
            'tables' => [
                'sales_by_status' => $this->statusSeries($sales),
                'receivables_by_party' => $ar['by_party'] ?? [],
            ],
            'insights' => $this->executiveInsights($ar, $profit, $delivery, $production),
        ];
    }

    /** @return array<string, mixed> */
    private function supplyChain(array $filters): array
    {
        $plans = SupplyPlan::query()->with(['product', 'productVariant']);
        $this->applyPlanningDate($plans, $filters);
        if ($filters['status'] ?? null) {
            $plans->where('status', $filters['status']);
        }
        $inventory = InventoryBalance::query();
        if ($filters['warehouse_id'] ?? null) {
            $inventory->where('warehouse_id', $filters['warehouse_id']);
        }
        $purchase = $this->purchaseQuery($filters);
        $shortages = (clone $plans)->whereColumn('required_quantity', '>', 'available_quantity')->orderByDesc(DB::raw('(required_quantity - available_quantity)'))->limit(8)->get();

        return [
            'kpis' => [
                ['key' => 'required_quantity', 'label' => 'Required supply', 'value' => (float) (clone $plans)->sum('required_quantity'), 'format' => 'quantity'],
                ['key' => 'available_quantity', 'label' => 'Available supply', 'value' => (float) (clone $plans)->sum('available_quantity'), 'format' => 'quantity'],
                ['key' => 'planned_production', 'label' => 'Planned production', 'value' => (float) (clone $plans)->sum('planned_production_quantity'), 'format' => 'quantity'],
                ['key' => 'shortage_count', 'label' => 'Supply shortfalls', 'value' => $shortages->count(), 'format' => 'number'],
                ['key' => 'stock_available', 'label' => 'Available stock', 'value' => (float) (clone $inventory)->selectRaw('COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) AS available')->value('available'), 'format' => 'quantity'],
                ['key' => 'open_purchase_orders', 'label' => 'Open Purchase Orders', 'value' => (clone $purchase)->whereNotIn('status', ['cancelled', 'closed', 'received'])->count(), 'format' => 'number'],
            ],
            'series' => ['planning_by_period' => $this->planningSeries($plans)],
            'tables' => ['shortages' => $shortages->map(fn (SupplyPlan $plan): array => ['id' => $plan->id, 'product' => $plan->product?->name, 'variant' => $plan->productVariant?->sku, 'required_quantity' => (float) $plan->required_quantity, 'available_quantity' => (float) $plan->available_quantity, 'shortage_quantity' => max(0, (float) $plan->required_quantity - (float) $plan->available_quantity)])->values()->all()],
            'insights' => $shortages->take(5)->map(fn (SupplyPlan $plan): array => ['code' => 'demand_without_available_stock', 'severity' => 'critical', 'title' => 'Supply shortfall on plan '.$plan->id, 'description' => 'Required quantity exceeds available quantity by '.round(max(0, (float) $plan->required_quantity - (float) $plan->available_quantity), 4).'.', 'source' => 'supply_plans'])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function production(array $filters): array
    {
        $query = $this->productionQuery($filters);
        $status = $this->statusSeries($query);
        $rejected = (float) (clone $query)->sum('rejected_quantity');
        $completed = (float) (clone $query)->sum('completed_quantity');
        $planned = (float) (clone $query)->sum('planned_quantity');
        $delayed = (clone $query)->whereNotIn('status', ['completed', 'cancelled'])->whereDate('expected_completion_date', '<', now()->toDateString())->whereColumn('completed_quantity', '<', 'planned_quantity')->count();

        return [
            'kpis' => [
                ['key' => 'production_orders', 'label' => 'Production orders', 'value' => (clone $query)->count(), 'format' => 'number'],
                ['key' => 'planned_quantity', 'label' => 'Planned quantity', 'value' => $planned, 'format' => 'quantity'],
                ['key' => 'completed_quantity', 'label' => 'Completed quantity', 'value' => $completed, 'format' => 'quantity'],
                ['key' => 'rejected_quantity', 'label' => 'Rejected quantity', 'value' => $rejected, 'format' => 'quantity'],
                ['key' => 'completion_rate', 'label' => 'Completion rate', 'value' => $planned > 0 ? round(($completed / $planned) * 100, 2) : null, 'format' => 'percentage'],
                ['key' => 'delayed_orders', 'label' => 'Delayed orders', 'value' => $delayed, 'format' => 'number'],
            ],
            'series' => ['orders_by_status' => $status, 'completion_by_date' => $this->dateSeries($query, 'expected_completion_date', 'completed_quantity', 'completed_quantity')],
            'tables' => ['status_breakdown' => $status],
            'insights' => $delayed > 0 ? [['code' => 'production_delayed', 'severity' => 'warning', 'title' => 'Production orders need attention', 'description' => $delayed.' active order(s) are past expected completion with remaining quantity.', 'source' => 'production_orders']] : [],
        ];
    }

    /** @return array<string, mixed> */
    private function procurement(array $filters): array
    {
        $orders = $this->purchaseQuery($filters);
        $receipts = DB::table('goods_receipts')->whereIn('status', ['posted', 'accepted']);
        $this->applyDbDate($receipts, 'receipt_date', $filters);
        if ($filters['supplier_id'] ?? null) {
            $receipts->where('supplier_id', $filters['supplier_id']);
        }
        $delayed = (clone $orders)->whereNotIn('status', ['draft', 'cancelled', 'closed', 'received'])->whereDate('expected_delivery_date', '<', now()->toDateString())->count();
        $receiptCount = (clone $receipts)->count();

        return [
            'kpis' => [
                ['key' => 'purchase_orders', 'label' => 'Purchase Orders', 'value' => (clone $orders)->count(), 'format' => 'number'],
                ['key' => 'purchase_value', 'label' => 'Purchase value', 'value' => (float) (clone $orders)->sum('total_amount'), 'format' => 'amount'],
                ['key' => 'posted_receipts', 'label' => 'Posted receipts', 'value' => $receiptCount, 'format' => 'number'],
                ['key' => 'delayed_orders', 'label' => 'Delayed orders', 'value' => $delayed, 'format' => 'number'],
                ['key' => 'received_value', 'label' => 'Received goods value', 'value' => (float) (clone $orders)->join('purchase_order_items as dashboard_receipt_items', 'purchase_orders.id', '=', 'dashboard_receipt_items.purchase_order_id')->sum(DB::raw('dashboard_receipt_items.received_quantity * dashboard_receipt_items.unit_price')), 'format' => 'amount'],
            ],
            'series' => ['orders_by_date' => $this->dateSeries($orders, 'po_date', 'total_amount', 'purchase_value')],
            'tables' => ['orders_by_status' => $this->statusSeries($orders)],
            'insights' => $delayed > 0 ? [['code' => 'purchase_order_delayed', 'severity' => 'warning', 'title' => 'Purchase Orders are overdue', 'description' => $delayed.' non-terminal Purchase Order(s) are past their expected date.', 'source' => 'purchase_orders']] : [],
        ];
    }

    /** @return array<string, mixed> */
    private function warehouse(array $filters): array
    {
        $balances = InventoryBalance::query()->with('warehouse');
        if ($filters['warehouse_id'] ?? null) {
            $balances->where('warehouse_id', $filters['warehouse_id']);
        }
        $transactions = InventoryTransaction::query();
        if ($filters['warehouse_id'] ?? null) {
            $transactions->where('warehouse_id', $filters['warehouse_id']);
        }
        $this->applyDate($transactions, 'transaction_date', $filters);
        $byWarehouse = (clone $balances)->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')->select('warehouses.id', 'warehouses.name', DB::raw('SUM(quantity_on_hand) AS quantity_on_hand'), DB::raw('SUM(quantity_reserved) AS quantity_reserved'), DB::raw('SUM(quantity_on_hand - quantity_reserved) AS quantity_available'))->groupBy('warehouses.id', 'warehouses.name')->orderByDesc('quantity_available')->limit(10)->get();
        $moves = (clone $transactions)->select('transaction_type', DB::raw('COUNT(*) AS count'), DB::raw('SUM(quantity) AS quantity'))->groupBy('transaction_type')->orderByDesc('count')->get();

        return [
            'kpis' => [
                ['key' => 'balance_count', 'label' => 'Tracked balances', 'value' => (clone $balances)->count(), 'format' => 'number'],
                ['key' => 'quantity_on_hand', 'label' => 'Quantity on hand', 'value' => (float) (clone $balances)->sum('quantity_on_hand'), 'format' => 'quantity'],
                ['key' => 'quantity_reserved', 'label' => 'Quantity reserved', 'value' => (float) (clone $balances)->sum('quantity_reserved'), 'format' => 'quantity'],
                ['key' => 'quantity_available', 'label' => 'Quantity available', 'value' => (float) (clone $balances)->selectRaw('COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) AS available')->value('available'), 'format' => 'quantity'],
                ['key' => 'movement_count', 'label' => 'Movements', 'value' => (clone $transactions)->count(), 'format' => 'number'],
            ],
            'series' => ['movements_by_type' => $moves->map(fn (object $row): array => ['label' => $row->transaction_type, 'count' => (int) $row->count, 'quantity' => (float) $row->quantity])->values()->all()],
            'tables' => ['stock_by_warehouse' => $byWarehouse->map(fn (object $row): array => ['warehouse_id' => (int) $row->id, 'warehouse' => $row->name, 'quantity_on_hand' => (float) $row->quantity_on_hand, 'quantity_reserved' => (float) $row->quantity_reserved, 'quantity_available' => (float) $row->quantity_available])->values()->all()],
            'insights' => [],
        ];
    }

    private function salesQuery(array $filters): Builder
    {
        $query = SalesOrder::query();
        $this->applyDate($query, 'order_date', $filters);
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['warehouse_id'] ?? null) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        return $query;
    }

    private function purchaseQuery(array $filters): Builder
    {
        $query = PurchaseOrder::query();
        $this->applyDate($query, 'po_date', $filters);
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['supplier_id'] ?? null) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        return $query;
    }

    private function productionQuery(array $filters): Builder
    {
        $query = ProductionOrder::query();
        $this->applyDate($query, 'expected_completion_date', $filters);
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['product_id'] ?? null) {
            $query->where('product_id', $filters['product_id']);
        }
        if ($filters['product_variant_id'] ?? null) {
            $query->where('product_variant_id', $filters['product_variant_id']);
        }
        if ($filters['warehouse_id'] ?? null) {
            $query->where('issue_warehouse_id', $filters['warehouse_id']);
        }

        return $query;
    }

    private function deliveryQuery(array $filters): Builder
    {
        $query = Delivery::query();
        $this->applyDate($query, 'delivery_date', $filters);
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['warehouse_id'] ?? null) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        return $query;
    }

    private function dateSeries(Builder $query, string $dateColumn, string $sumColumn, string $valueKey): array
    {
        $query = clone $query;

        return $query->select($dateColumn, DB::raw('COUNT(*) AS count'), DB::raw('COALESCE(SUM('.$sumColumn.'), 0) AS value'))->groupBy($dateColumn)->orderBy($dateColumn)->limit(60)->get()->map(fn (object $row): array => ['period' => (string) $row->{$dateColumn}, 'count' => (int) $row->count, $valueKey => (float) $row->value])->values()->all();
    }

    private function statusSeries(Builder $query): array
    {
        $query = clone $query;

        return $query->select('status', DB::raw('COUNT(*) AS count'))->groupBy('status')->orderByDesc('count')->get()->map(fn (object $row): array => ['status' => $row->status, 'count' => (int) $row->count])->values()->all();
    }

    private function planningSeries(Builder $query): array
    {
        $query = clone $query;

        return $query->select('period_start', DB::raw('SUM(required_quantity) AS required_quantity'), DB::raw('SUM(available_quantity) AS available_quantity'), DB::raw('SUM(planned_production_quantity) AS planned_production_quantity'))->groupBy('period_start')->orderBy('period_start')->limit(60)->get()->map(fn (object $row): array => ['period' => (string) $row->period_start, 'required_quantity' => (float) $row->required_quantity, 'available_quantity' => (float) $row->available_quantity, 'planned_production_quantity' => (float) $row->planned_production_quantity])->values()->all();
    }

    private function applyDate(Builder $query, string $column, array $filters): void
    {
        if ($filters['date_from'] ?? null) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }
        if ($filters['date_to'] ?? null) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }

    private function applyPlanningDate(Builder $query, array $filters): void
    {
        if ($filters['date_from'] ?? null) {
            $query->whereDate('period_start', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] ?? null) {
            $query->whereDate('period_end', '<=', $filters['date_to']);
        }
    }

    private function applyDbDate($query, string $column, array $filters): void
    {
        if ($filters['date_from'] ?? null) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }
        if ($filters['date_to'] ?? null) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }

    private function normalizeFilters(array $filters): array
    {
        return array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
    }

    private function financeFilters(array $filters): array
    {
        return ['buyer_id' => $filters['buyer_id'] ?? null, 'customer_id' => $filters['customer_id'] ?? null, 'invoice_date_from' => $filters['date_from'] ?? null, 'invoice_date_to' => $filters['date_to'] ?? null];
    }

    private function executiveInsights(array $ar, array $profit, Builder $delivery, Builder $production): array
    {
        $insights = [];
        if (($ar['overdue_count'] ?? 0) > 0) {
            $insights[] = ['code' => 'invoice_overdue', 'severity' => 'critical', 'title' => 'Receivables require attention', 'description' => $ar['overdue_count'].' invoice(s) are overdue with outstanding balance.', 'source' => 'invoices'];
        }
        if (($profit['cost_data_complete'] ?? false) === false && ($profit['invoice_count'] ?? 0) > 0) {
            $insights[] = ['code' => 'profit_cost_incomplete', 'severity' => 'info', 'title' => 'Profit is withheld for incomplete cost data', 'description' => 'The existing finance rule will not present gross profit or margin until every invoice line has a usable cost.', 'source' => 'invoices/product_variants/products'];
        }
        $lateDeliveries = (clone $delivery)->whereNotIn('status', ['delivered', 'completed', 'cancelled'])->whereNotNull('expected_delivery_date')->whereDate('expected_delivery_date', '<', now()->toDateString())->count();
        if ($lateDeliveries > 0) {
            $insights[] = ['code' => 'delivery_delayed', 'severity' => 'warning', 'title' => 'Deliveries are overdue', 'description' => $lateDeliveries.' active delivery(s) are past their expected date.', 'source' => 'deliveries'];
        }
        $lateProduction = (clone $production)->whereNotIn('status', ['completed', 'cancelled'])->whereDate('expected_completion_date', '<', now()->toDateString())->whereColumn('completed_quantity', '<', 'planned_quantity')->count();
        if ($lateProduction > 0) {
            $insights[] = ['code' => 'production_delayed', 'severity' => 'warning', 'title' => 'Production is behind schedule', 'description' => $lateProduction.' production order(s) are past expected completion with remaining quantity.', 'source' => 'production_orders'];
        }

        return $insights;
    }
}
