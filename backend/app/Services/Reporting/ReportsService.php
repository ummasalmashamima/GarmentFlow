<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Delivery;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Services\Finance\AccountsPayableService;
use App\Services\Finance\AccountsReceivableService;
use App\Services\Finance\ProfitSummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class ReportsService
{
    private const REPORTS = [
        'sales' => 'Sales report',
        'purchase' => 'Purchase report',
        'stock' => 'Stock report',
        'profit' => 'Profit report',
        'production' => 'Production report',
        'payment' => 'Payment report',
        'delivery' => 'Delivery report',
        'inventory-movement' => 'Inventory movement report',
        'supplier-performance' => 'Supplier performance report',
        'buyer-customer' => 'Buyer and customer report',
    ];

    public function __construct(
        private readonly AccountsReceivableService $receivables,
        private readonly AccountsPayableService $payables,
        private readonly ProfitSummaryService $profit,
    ) {}

    public function supported(): array
    {
        return self::REPORTS;
    }

    /** @return array<string, mixed> */
    public function run(string $report, array $filters): array
    {
        abort_unless(isset(self::REPORTS[$report]), 404, 'Unsupported report.');
        $filters = $this->normalizeFilters($filters);

        return match ($report) {
            'sales' => $this->sales($filters),
            'purchase' => $this->purchase($filters),
            'stock' => $this->stock($filters),
            'profit' => $this->profitReport($filters),
            'production' => $this->production($filters),
            'payment' => $this->payment($filters),
            'delivery' => $this->delivery($filters),
            'inventory-movement' => $this->inventoryMovement($filters),
            'supplier-performance' => $this->supplierPerformance($filters),
            'buyer-customer' => $this->buyerCustomer($filters),
        };
    }

    /** @return array<string, mixed> */
    private function sales(array $filters): array
    {
        $query = $this->salesQuery($filters);
        $summary = [
            'order_count' => (clone $query)->count(),
            'total_amount' => (float) (clone $query)->sum('total_amount'),
            'ordered_quantity' => (float) (clone $query)->sum('ordered_quantity'),
            'confirmed_quantity' => (float) (clone $query)->sum('confirmed_quantity'),
            'delivered_quantity' => (float) (clone $query)->sum('delivered_quantity'),
            'remaining_quantity' => (float) (clone $query)->sum('remaining_quantity'),
        ];
        $query->with(['buyer', 'customer', 'warehouse']);
        $rows = $this->paginate($query, $filters, ['id', 'order_date', 'required_delivery_date', 'status', 'total_amount', 'ordered_quantity', 'delivered_quantity']);
        $rows['data'] = array_map(fn (SalesOrder $row): array => [
            'id' => $row->id,
            'sales_order_number' => $row->sales_order_number,
            'order_date' => $row->order_date?->format('Y-m-d'),
            'required_delivery_date' => $row->required_delivery_date?->format('Y-m-d'),
            'status' => $row->status,
            'buyer' => $row->buyer?->name,
            'customer' => $row->customer?->name,
            'warehouse' => $row->warehouse?->name,
            'total_amount' => (float) $row->total_amount,
            'ordered_quantity' => (float) $row->ordered_quantity,
            'confirmed_quantity' => (float) $row->confirmed_quantity,
            'delivered_quantity' => (float) $row->delivered_quantity,
            'remaining_quantity' => (float) $row->remaining_quantity,
        ], $rows['data']);

        return $this->result('sales', $filters, $summary, $rows);
    }

    /** @return array<string, mixed> */
    private function purchase(array $filters): array
    {
        $query = $this->purchaseQuery($filters);
        $summary = [
            'purchase_order_count' => (clone $query)->count(),
            'total_amount' => (float) (clone $query)->sum('total_amount'),
            'ordered_quantity' => (float) (clone $query)->join('purchase_order_items as summary_items', 'purchase_orders.id', '=', 'summary_items.purchase_order_id')->sum('summary_items.quantity'),
            'received_quantity' => (float) (clone $query)->join('purchase_order_items as summary_received_items', 'purchase_orders.id', '=', 'summary_received_items.purchase_order_id')->sum('summary_received_items.received_quantity'),
        ];
        $query->with(['supplier', 'items']);
        $rows = $this->paginate($query, $filters, ['id', 'po_date', 'expected_delivery_date', 'status', 'total_amount']);
        $rows['data'] = array_map(fn (PurchaseOrder $row): array => [
            'id' => $row->id,
            'purchase_order_number' => $row->purchase_order_number,
            'po_date' => $row->po_date?->format('Y-m-d'),
            'expected_delivery_date' => $row->expected_delivery_date?->format('Y-m-d'),
            'status' => $row->status,
            'supplier' => $row->supplier?->name,
            'currency' => $row->currency,
            'total_amount' => (float) $row->total_amount,
            'receipt_progress' => $this->purchaseReceiptProgress($row),
        ], $rows['data']);

        return $this->result('purchase', $filters, $summary, $rows);
    }

    /** @return array<string, mixed> */
    private function stock(array $filters): array
    {
        $query = InventoryBalance::query()->with(['warehouse', 'warehouseLocation', 'material', 'product', 'productVariant', 'unit']);
        $this->applyStockFilters($query, $filters);
        $summaryRow = (clone $query)->selectRaw('COUNT(*) AS balance_count, COALESCE(SUM(quantity_on_hand), 0) AS quantity_on_hand, COALESCE(SUM(quantity_reserved), 0) AS quantity_reserved, COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) AS quantity_available')->first();
        $summary = [
            'balance_count' => (int) ($summaryRow->balance_count ?? 0),
            'quantity_on_hand' => (float) ($summaryRow->quantity_on_hand ?? 0),
            'quantity_reserved' => (float) ($summaryRow->quantity_reserved ?? 0),
            'quantity_available' => (float) ($summaryRow->quantity_available ?? 0),
            'valuation_available' => null,
            'valuation_note' => 'Inventory balances have no canonical cost field; stock valuation is intentionally not fabricated.',
        ];
        $rows = $this->paginate($query, $filters, ['id', 'stock_key', 'quantity_on_hand', 'quantity_reserved', 'created_at']);
        $rows['data'] = array_map(fn (InventoryBalance $row): array => [
            'id' => $row->id,
            'stock_key' => $row->stock_key,
            'item_type' => $row->item_type,
            'material' => $row->material?->name,
            'product' => $row->product?->name,
            'variant' => $row->productVariant?->sku,
            'unit' => $row->unit?->code ?? $row->unit?->name,
            'warehouse' => $row->warehouse?->name,
            'location' => $row->warehouseLocation?->name,
            'quantity_on_hand' => (float) $row->quantity_on_hand,
            'quantity_reserved' => (float) $row->quantity_reserved,
            'quantity_available' => (float) $row->available_quantity,
            'status' => $row->status,
        ], $rows['data']);

        return $this->result('stock', $filters, $summary, $rows);
    }

    /** @return array<string, mixed> */
    private function profitReport(array $filters): array
    {
        $summary = $this->profit->summary([
            'buyer_id' => $filters['buyer_id'] ?? null,
            'customer_id' => $filters['customer_id'] ?? null,
            'invoice_date_from' => $filters['date_from'] ?? null,
            'invoice_date_to' => $filters['date_to'] ?? null,
        ]);
        $query = $this->invoiceQuery($filters);
        $query->with(['buyer', 'customer', 'salesOrder']);
        $rows = $this->paginate($query, $filters, ['id', 'invoice_date', 'due_date', 'status', 'total_amount', 'paid_amount', 'due_amount']);
        $rows['data'] = array_map(fn (Invoice $row): array => [
            'id' => $row->id,
            'invoice_number' => $row->invoice_number,
            'invoice_date' => $row->invoice_date?->format('Y-m-d'),
            'due_date' => $row->due_date?->format('Y-m-d'),
            'status' => $row->status,
            'buyer' => $row->buyer?->name,
            'customer' => $row->customer?->name,
            'sales_order_number' => $row->salesOrder?->sales_order_number,
            'revenue' => (float) $row->total_amount,
            'paid_amount' => (float) $row->paid_amount,
            'due_amount' => (float) $row->due_amount,
            'gross_profit' => null,
            'margin' => null,
            'profit_note' => 'Per-line cost is intentionally withheld here; use the summary cost completeness flag.',
        ], $rows['data']);

        return $this->result('profit', $filters, $summary, $rows);
    }

    /** @return array<string, mixed> */
    private function production(array $filters): array
    {
        $query = ProductionOrder::query()->with(['product', 'productVariant', 'issueWarehouse']);
        $this->applyProductionFilters($query, $filters);
        $summary = [
            'production_order_count' => (clone $query)->count(),
            'planned_quantity' => (float) (clone $query)->sum('planned_quantity'),
            'completed_quantity' => (float) (clone $query)->sum('completed_quantity'),
            'rejected_quantity' => (float) (clone $query)->sum('rejected_quantity'),
            'remaining_quantity' => (float) (clone $query)->selectRaw('COALESCE(SUM(planned_quantity - completed_quantity), 0) AS total')->value('total'),
        ];
        $rows = $this->paginate($query, $filters, ['id', 'expected_completion_date', 'status', 'planned_quantity', 'completed_quantity', 'rejected_quantity']);
        $rows['data'] = array_map(fn (ProductionOrder $row): array => [
            'id' => $row->id,
            'order_number' => $row->order_number,
            'status' => $row->status,
            'product' => $row->product?->name,
            'variant' => $row->productVariant?->sku,
            'warehouse' => $row->issueWarehouse?->name,
            'planned_quantity' => (float) $row->planned_quantity,
            'completed_quantity' => (float) $row->completed_quantity,
            'rejected_quantity' => (float) $row->rejected_quantity,
            'remaining_quantity' => max(0, (float) $row->planned_quantity - (float) $row->completed_quantity),
            'expected_completion_date' => $row->expected_completion_date?->format('Y-m-d'),
            'completed_date' => $row->completed_date?->format('Y-m-d'),
        ], $rows['data']);

        return $this->result('production', $filters, $summary, $rows);
    }

    /** @return array<string, mixed> */
    private function payment(array $filters): array
    {
        $query = Payment::query()->with(['invoice', 'buyer', 'customer']);
        $this->applyPaymentFilters($query, $filters);
        $summary = [
            'payment_count' => (clone $query)->count(),
            'received_amount' => (float) (clone $query)->where('status', 'received')->sum('amount'),
            'voided_amount' => (float) (clone $query)->where('status', 'voided')->sum('amount'),
        ];
        $rows = $this->paginate($query, $filters, ['id', 'payment_date', 'amount', 'status', 'payment_number']);
        $rows['data'] = array_map(fn (Payment $row): array => [
            'id' => $row->id,
            'payment_number' => $row->payment_number,
            'payment_date' => $row->payment_date?->format('Y-m-d'),
            'invoice_number' => $row->invoice?->invoice_number,
            'buyer' => $row->buyer?->name,
            'customer' => $row->customer?->name,
            'amount' => (float) $row->amount,
            'payment_method' => $row->payment_method,
            'reference_number' => $row->reference_number,
            'status' => $row->status,
        ], $rows['data']);

        return $this->result('payment', $filters, $summary, $rows);
    }

    /** @return array<string, mixed> */
    private function delivery(array $filters): array
    {
        $query = Delivery::query()->with(['salesOrder', 'warehouse']);
        $this->applyDeliveryFilters($query, $filters);
        $summary = [
            'delivery_count' => (clone $query)->count(),
            'ordered_quantity' => (float) (clone $query)->sum('ordered_quantity'),
            'dispatched_quantity' => (float) (clone $query)->sum('dispatched_quantity'),
            'delivered_quantity' => (float) (clone $query)->sum('delivered_quantity'),
            'remaining_quantity' => (float) (clone $query)->sum('remaining_quantity'),
        ];
        $rows = $this->paginate($query, $filters, ['id', 'delivery_date', 'expected_delivery_date', 'status', 'dispatched_quantity', 'delivered_quantity']);
        $rows['data'] = array_map(fn (Delivery $row): array => [
            'id' => $row->id,
            'delivery_number' => $row->delivery_number,
            'sales_order_number' => $row->salesOrder?->sales_order_number,
            'warehouse' => $row->warehouse?->name,
            'delivery_date' => $row->delivery_date?->format('Y-m-d'),
            'expected_delivery_date' => $row->expected_delivery_date?->format('Y-m-d'),
            'status' => $row->status,
            'carrier_name' => $row->carrier_name,
            'tracking_number' => $row->tracking_number,
            'ordered_quantity' => (float) $row->ordered_quantity,
            'dispatched_quantity' => (float) $row->dispatched_quantity,
            'delivered_quantity' => (float) $row->delivered_quantity,
            'remaining_quantity' => (float) $row->remaining_quantity,
        ], $rows['data']);

        return $this->result('delivery', $filters, $summary, $rows);
    }

    /** @return array<string, mixed> */
    private function inventoryMovement(array $filters): array
    {
        $query = InventoryTransaction::query()->with(['warehouse', 'warehouseLocation', 'material', 'product', 'productVariant', 'unit']);
        $this->applyMovementFilters($query, $filters);
        $summary = [
            'transaction_count' => (clone $query)->count(),
            'quantity_in' => (float) (clone $query)->whereIn('transaction_type', ['STOCK_IN', 'TRANSFER_IN', 'ADJUSTMENT_IN'])->sum('quantity'),
            'quantity_out' => (float) (clone $query)->whereIn('transaction_type', ['STOCK_OUT', 'TRANSFER_OUT', 'ADJUSTMENT_OUT'])->sum('quantity'),
        ];
        $rows = $this->paginate($query, $filters, ['id', 'transaction_date', 'quantity', 'transaction_type', 'transaction_number']);
        $rows['data'] = array_map(fn (InventoryTransaction $row): array => [
            'id' => $row->id,
            'transaction_number' => $row->transaction_number,
            'transaction_date' => $row->transaction_date?->toDateTimeString(),
            'transaction_type' => $row->transaction_type,
            'quantity' => (float) $row->quantity,
            'reference_type' => $row->reference_type,
            'reference_id' => $row->reference_id,
            'material' => $row->material?->name,
            'product' => $row->product?->name,
            'variant' => $row->productVariant?->sku,
            'warehouse' => $row->warehouse?->name,
            'location' => $row->warehouseLocation?->name,
        ], $rows['data']);

        return $this->result('inventory-movement', $filters, $summary, $rows);
    }

    /** @return array<string, mixed> */
    private function supplierPerformance(array $filters): array
    {
        $query = $this->supplierPerformanceQuery($filters);
        $all = (clone $query)->get();
        $summary = [
            'supplier_count' => $all->count(),
            'purchase_order_count' => (int) $all->sum('purchase_order_count'),
            'ordered_quantity' => (float) $all->sum('ordered_quantity'),
            'received_quantity' => (float) $all->sum('received_quantity'),
            'accepted_quantity' => (float) $all->sum('accepted_quantity'),
            'rejected_quantity' => (float) $all->sum('rejected_quantity'),
            'rejection_rate' => $all->sum('received_quantity') > 0 ? round(((float) $all->sum('rejected_quantity') / (float) $all->sum('received_quantity')) * 100, 2) : null,
        ];
        $rows = $this->paginate($query, $filters, ['supplier_id', 'supplier_name', 'purchase_order_count', 'ordered_quantity', 'rejection_rate'], 'supplier_name');
        $rows['data'] = array_map(static fn (object $row): array => [
            'supplier_id' => (int) $row->supplier_id,
            'supplier_code' => $row->supplier_code,
            'supplier_name' => $row->supplier_name,
            'purchase_order_count' => (int) $row->purchase_order_count,
            'ordered_quantity' => (float) $row->ordered_quantity,
            'received_quantity' => (float) $row->received_quantity,
            'accepted_quantity' => (float) $row->accepted_quantity,
            'rejected_quantity' => (float) $row->rejected_quantity,
            'rejection_rate' => $row->received_quantity > 0 ? round(((float) $row->rejected_quantity / (float) $row->received_quantity) * 100, 2) : null,
        ], $rows['data']);

        return $this->result('supplier-performance', $filters, $summary, $rows);
    }

    /** @return array<string, mixed> */
    private function buyerCustomer(array $filters): array
    {
        $query = DB::table('sales_orders')
            ->leftJoin('buyers', 'buyers.id', '=', 'sales_orders.buyer_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->whereNull('sales_orders.deleted_at')
            ->select([
                DB::raw('COALESCE(buyers.id, customers.id, 0) AS party_id'),
                DB::raw("CASE WHEN buyers.id IS NOT NULL THEN 'buyer' ELSE 'customer' END AS party_type"),
                DB::raw("COALESCE(buyers.code, customers.code, '') AS party_code"),
                DB::raw("COALESCE(buyers.name, customers.name, 'Unassigned') AS party_name"),
                DB::raw('COUNT(sales_orders.id) AS order_count'),
                DB::raw('COALESCE(SUM(sales_orders.total_amount), 0) AS total_amount'),
                DB::raw('COALESCE(SUM(sales_orders.ordered_quantity), 0) AS ordered_quantity'),
                DB::raw('COALESCE(SUM(sales_orders.delivered_quantity), 0) AS delivered_quantity'),
            ])
            ->groupBy('buyers.id', 'customers.id', 'buyers.code', 'customers.code', 'buyers.name', 'customers.name');
        $this->applyPartyQueryFilters($query, $filters, 'sales_orders.order_date');
        $summaryRows = (clone $query)->get();
        $summary = [
            'party_count' => $summaryRows->count(),
            'order_count' => (int) $summaryRows->sum('order_count'),
            'total_amount' => (float) $summaryRows->sum('total_amount'),
            'ordered_quantity' => (float) $summaryRows->sum('ordered_quantity'),
            'delivered_quantity' => (float) $summaryRows->sum('delivered_quantity'),
        ];
        $rows = $this->paginate($query, $filters, ['party_id', 'party_name', 'order_count', 'total_amount', 'ordered_quantity'], 'party_name');
        $rows['data'] = array_map(static fn (object $row): array => [
            'party_id' => (int) $row->party_id,
            'party_type' => $row->party_type,
            'party_code' => $row->party_code,
            'party_name' => $row->party_name,
            'order_count' => (int) $row->order_count,
            'total_amount' => (float) $row->total_amount,
            'ordered_quantity' => (float) $row->ordered_quantity,
            'delivered_quantity' => (float) $row->delivered_quantity,
        ], $rows['data']);

        return $this->result('buyer-customer', $filters, $summary, $rows);
    }

    private function salesQuery(array $filters): Builder
    {
        $query = SalesOrder::query();
        $this->applyDate($query, 'order_date', $filters);
        $this->applyStatusPartyProductFilters($query, $filters, 'items.product.category_id');
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
        if ($filters['search'] ?? null) {
            $query->where(fn (Builder $q) => $q->where('purchase_order_number', 'like', '%'.$filters['search'].'%')->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$filters['search'].'%')->orWhere('code', 'like', '%'.$filters['search'].'%')));
        }

        return $query;
    }

    private function invoiceQuery(array $filters): Builder
    {
        $query = Invoice::query();
        $this->applyDate($query, 'invoice_date', $filters);
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        foreach (['buyer_id', 'customer_id', 'warehouse_id'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['search'] ?? null) {
            $query->where(fn (Builder $q) => $q->where('invoice_number', 'like', '%'.$filters['search'].'%')->orWhereHas('salesOrder', fn (Builder $order) => $order->where('sales_order_number', 'like', '%'.$filters['search'].'%')));
        }

        return $query;
    }

    private function applyProductionFilters(Builder $query, array $filters): void
    {
        $this->applyDate($query, 'expected_completion_date', $filters);
        foreach (['status', 'product_id', 'product_variant_id', 'issue_warehouse_id'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['category_id'] ?? null) {
            $query->whereHas('product', fn (Builder $q) => $q->where('category_id', $filters['category_id']));
        }
        if ($filters['search'] ?? null) {
            $query->where(fn (Builder $q) => $q->where('order_number', 'like', '%'.$filters['search'].'%')->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', '%'.$filters['search'].'%')->orWhere('code', 'like', '%'.$filters['search'].'%')));
        }
    }

    private function applyPaymentFilters(Builder $query, array $filters): void
    {
        $this->applyDate($query, 'payment_date', $filters);
        foreach (['status', 'buyer_id', 'customer_id'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['search'] ?? null) {
            $query->where(fn (Builder $q) => $q->where('payment_number', 'like', '%'.$filters['search'].'%')->orWhere('reference_number', 'like', '%'.$filters['search'].'%')->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('invoice_number', 'like', '%'.$filters['search'].'%')));
        }
    }

    private function applyDeliveryFilters(Builder $query, array $filters): void
    {
        $this->applyDate($query, 'delivery_date', $filters);
        foreach (['status', 'warehouse_id'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['search'] ?? null) {
            $query->where(fn (Builder $q) => $q->where('delivery_number', 'like', '%'.$filters['search'].'%')->orWhere('tracking_number', 'like', '%'.$filters['search'].'%')->orWhereHas('salesOrder', fn (Builder $order) => $order->where('sales_order_number', 'like', '%'.$filters['search'].'%')));
        }
    }

    private function applyMovementFilters(Builder $query, array $filters): void
    {
        $this->applyDate($query, 'transaction_date', $filters);
        foreach (['transaction_type', 'warehouse_id', 'warehouse_location_id', 'material_id', 'product_id', 'product_variant_id'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['search'] ?? null) {
            $query->where(fn (Builder $q) => $q->where('transaction_number', 'like', '%'.$filters['search'].'%')->orWhere('reference_type', 'like', '%'.$filters['search'].'%')->orWhereHas('material', fn (Builder $m) => $m->where('name', 'like', '%'.$filters['search'].'%')->orWhere('code', 'like', '%'.$filters['search'].'%')));
        }
    }

    private function applyStockFilters(Builder $query, array $filters): void
    {
        foreach (['status', 'item_type', 'warehouse_id', 'warehouse_location_id', 'material_id', 'product_id', 'product_variant_id', 'unit_id'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['category_id'] ?? null) {
            $query->whereHas('product', fn (Builder $q) => $q->where('category_id', $filters['category_id']));
        }
        if ($filters['search'] ?? null) {
            $query->where(fn (Builder $q) => $q->where('stock_key', 'like', '%'.$filters['search'].'%')->orWhereHas('material', fn (Builder $m) => $m->where('name', 'like', '%'.$filters['search'].'%')->orWhere('code', 'like', '%'.$filters['search'].'%'))->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', '%'.$filters['search'].'%')->orWhere('code', 'like', '%'.$filters['search'].'%')));
        }
    }

    private function applyStatusPartyProductFilters(Builder $query, array $filters, string $categoryRelation): void
    {
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        foreach (['buyer_id', 'customer_id'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['product_id'] ?? null) {
            $query->whereHas('items', fn (Builder $q) => $q->where('product_id', $filters['product_id']));
        }
        if ($filters['product_variant_id'] ?? null) {
            $query->whereHas('items', fn (Builder $q) => $q->where('product_variant_id', $filters['product_variant_id']));
        }
        if ($filters['category_id'] ?? null) {
            $query->whereHas('items.product', fn (Builder $q) => $q->where('category_id', $filters['category_id']));
        }
        if ($filters['search'] ?? null) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn (Builder $q) => $q->where('sales_order_number', 'like', $search)->orWhereHas('buyer', fn (Builder $b) => $b->where('name', 'like', $search)->orWhere('code', 'like', $search))->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', $search)->orWhere('code', 'like', $search)));
        }
    }

    private function applyPartyQueryFilters($query, array $filters, string $dateColumn): void
    {
        if ($filters['status'] ?? null) {
            $query->where('sales_orders.status', $filters['status']);
        }
        if ($filters['buyer_id'] ?? null) {
            $query->where('sales_orders.buyer_id', $filters['buyer_id']);
        }
        if ($filters['customer_id'] ?? null) {
            $query->where('sales_orders.customer_id', $filters['customer_id']);
        }
        if ($filters['date_from'] ?? null) {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }
        if ($filters['date_to'] ?? null) {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }
        if ($filters['search'] ?? null) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('buyers.name', 'like', $search)->orWhere('customers.name', 'like', $search)->orWhere('buyers.code', 'like', $search)->orWhere('customers.code', 'like', $search));
        }
    }

    private function supplierPerformanceQuery(array $filters)
    {
        $receiptTotals = DB::table('goods_receipt_items')
            ->select([
                'purchase_order_item_id',
                DB::raw('COALESCE(SUM(received_quantity), 0) AS received_quantity'),
                DB::raw('COALESCE(SUM(accepted_quantity), 0) AS accepted_quantity'),
                DB::raw('COALESCE(SUM(rejected_quantity), 0) AS rejected_quantity'),
            ])
            ->groupBy('purchase_order_item_id');

        $query = Supplier::query()
            ->leftJoin('purchase_orders', function (JoinClause $join) use ($filters): void {
                $join->on('suppliers.id', '=', 'purchase_orders.supplier_id')->whereNull('purchase_orders.deleted_at');
                if ($filters['date_from'] ?? null) {
                    $join->whereDate('purchase_orders.po_date', '>=', $filters['date_from']);
                }
                if ($filters['date_to'] ?? null) {
                    $join->whereDate('purchase_orders.po_date', '<=', $filters['date_to']);
                }
                if ($filters['status'] ?? null) {
                    $join->where('purchase_orders.status', $filters['status']);
                }
            })
            ->leftJoin('purchase_order_items', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->leftJoinSub($receiptTotals, 'supplier_receipt_totals', function (JoinClause $join): void {
                $join->on('purchase_order_items.id', '=', 'supplier_receipt_totals.purchase_order_item_id');
            })
            ->select([
                'suppliers.id AS supplier_id',
                'suppliers.code AS supplier_code',
                'suppliers.name AS supplier_name',
                DB::raw('COUNT(DISTINCT purchase_orders.id) AS purchase_order_count'),
                DB::raw('COALESCE(SUM(purchase_order_items.quantity), 0) AS ordered_quantity'),
                DB::raw('COALESCE(SUM(supplier_receipt_totals.received_quantity), 0) AS received_quantity'),
                DB::raw('COALESCE(SUM(supplier_receipt_totals.accepted_quantity), 0) AS accepted_quantity'),
                DB::raw('COALESCE(SUM(supplier_receipt_totals.rejected_quantity), 0) AS rejected_quantity'),
            ])
            ->groupBy('suppliers.id', 'suppliers.code', 'suppliers.name');
        if ($filters['supplier_id'] ?? null) {
            $query->where('suppliers.id', $filters['supplier_id']);
        }
        if ($filters['search'] ?? null) {
            $query->where(fn ($q) => $q->where('suppliers.code', 'like', '%'.$filters['search'].'%')->orWhere('suppliers.name', 'like', '%'.$filters['search'].'%'));
        }

        return $query;
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

    private function purchaseReceiptProgress(PurchaseOrder $order): float
    {
        $ordered = (float) $order->items->sum('quantity');

        return $ordered > 0 ? round(((float) $order->items->sum('received_quantity') / $ordered) * 100, 2) : 0.0;
    }

    /** @return array<string, mixed> */
    private function result(string $report, array $filters, array $summary, array $rows): array
    {
        return ['report' => $report, 'label' => self::REPORTS[$report], 'filters' => $filters, 'summary' => $summary, 'rows' => $rows];
    }

    /** @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $filters['per_page'] = min(100, max(1, (int) ($filters['per_page'] ?? 15)));
        $filters['direction'] = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
    }

    /** @return array<string, mixed> */
    private function paginate($query, array $filters, array $allowedSort, string $defaultSort = 'id'): array
    {
        $sort = in_array(($filters['sort'] ?? $defaultSort), $allowedSort, true) ? ($filters['sort'] ?? $defaultSort) : $defaultSort;
        $direction = $filters['direction'] ?? 'desc';
        $paginator = $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
        $data = $paginator->getCollection()->all();
        $result = $paginator->toArray();
        $result['data'] = $data;

        return $result;
    }
}
