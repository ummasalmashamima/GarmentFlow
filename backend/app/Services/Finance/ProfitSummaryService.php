<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Invoice;

final class ProfitSummaryService
{
    /** @param array<string, mixed> $filters */
    public function summary(array $filters): array
    {
        $query = Invoice::query()
            ->with(['items.product', 'items.productVariant.product'])
            ->whereNotIn('status', [InvoiceWorkflow::DRAFT, InvoiceWorkflow::CANCELLED]);
        foreach (['buyer_id', 'customer_id', 'status'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['invoice_date_from' => ['invoice_date', '>='], 'invoice_date_to' => ['invoice_date', '<=']] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $invoices = $query->get();
        $grossSales = (float) $invoices->sum(fn (Invoice $invoice): float => (float) $invoice->total_amount);
        $cogs = 0.0;
        $missing = [];
        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                $unitCost = $this->unitCost($item);
                if ($unitCost === null) {
                    $missing[] = [
                        'invoice_number' => $invoice->invoice_number,
                        'product_code' => $item->product?->code,
                        'product_variant_sku' => $item->productVariant?->sku,
                        'quantity' => number_format((float) $item->quantity, 4, '.', ''),
                    ];

                    continue;
                }
                $cogs += (float) $item->quantity * $unitCost;
            }
        }
        $costComplete = $missing === [];
        $grossProfit = $costComplete ? $grossSales - $cogs : null;

        return [
            'gross_sales' => $this->decimal($grossSales),
            'cost_of_goods_sold' => $this->decimal($cogs),
            'gross_profit' => $grossProfit === null ? null : $this->decimal($grossProfit),
            'profit_margin' => $grossProfit === null || $grossSales <= 0 ? null : $this->decimal(($grossProfit / $grossSales) * 100),
            'cost_data_complete' => $costComplete,
            'unpriced_line_count' => count($missing),
            'missing_cost_items' => $missing,
            'limitation' => $costComplete ? null : 'Gross profit and margin are withheld because one or more invoice lines lack a ProductVariant cost_price and Product standard_cost. No cost was fabricated.',
            'invoice_count' => $invoices->count(),
        ];
    }

    private function unitCost(object $item): ?float
    {
        $variantCost = $item->productVariant?->cost_price;
        if ($variantCost !== null && (float) $variantCost > 0) {
            return (float) $variantCost;
        }
        $productCost = $item->product?->standard_cost;
        if ($productCost !== null && (float) $productCost > 0) {
            return (float) $productCost;
        }

        return null;
    }

    private function decimal(float|int|string $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
