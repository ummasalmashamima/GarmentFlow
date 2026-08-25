<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\PurchaseOrder;
use App\Services\Procurement\ProcurementWorkflow;

final class AccountsPayableService
{
    /** @param array<string, mixed> $filters */
    public function summary(array $filters): array
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->whereIn('status', [
                ProcurementWorkflow::APPROVED,
                ProcurementWorkflow::SENT_TO_SUPPLIER,
                ProcurementWorkflow::PARTIALLY_RECEIVED,
                ProcurementWorkflow::FULLY_RECEIVED,
                ProcurementWorkflow::CLOSED,
            ]);
        foreach (['supplier_id', 'status'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['po_date_from' => ['po_date', '>='], 'po_date_to' => ['po_date', '<=']] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $orders = $query->get();
        $supplierBreakdown = $orders->groupBy('supplier_id')->map(function ($rows): array {
            $supplier = $rows->first()->supplier;
            $total = $rows->sum(fn (PurchaseOrder $order): float => (float) $order->total_amount);
            $receivedValue = $rows->sum(fn (PurchaseOrder $order): float => $this->receivedValue($order));

            return [
                'supplier_id' => $supplier?->getKey(),
                'supplier_code' => $supplier?->code,
                'supplier_name' => $supplier?->name,
                'purchase_order_count' => $rows->count(),
                'total_payable' => $this->decimal($total),
                'goods_received_value' => $this->decimal($receivedValue),
                'outstanding_payable' => $this->decimal($total),
            ];
        })->values()->all();

        return [
            'purchase_order_count' => $orders->count(),
            'total_payable' => $this->decimal($orders->sum(fn (PurchaseOrder $order): float => (float) $order->total_amount)),
            'goods_received_value' => $this->decimal($orders->sum(fn (PurchaseOrder $order): float => $this->receivedValue($order))),
            'outstanding_payable' => $this->decimal($orders->sum(fn (PurchaseOrder $order): float => (float) $order->total_amount)),
            'supplier_breakdown' => $supplierBreakdown,
            'limitation' => 'Accounts Payable is derived from eligible Purchase Orders. No supplier payment ledger exists in the current GarmentFlow architecture, so outstanding payable equals eligible Purchase Order total.',
        ];
    }

    private function receivedValue(PurchaseOrder $order): float
    {
        return (float) $order->items->sum(function ($item): float {
            $quantity = (float) $item->quantity;
            if ($quantity <= 0) {
                return 0.0;
            }

            return ((float) $item->received_quantity / $quantity) * (float) $item->line_total;
        });
    }

    private function decimal(float|int|string $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
