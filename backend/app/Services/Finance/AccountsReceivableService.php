<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

final class AccountsReceivableService
{
    /** @param array<string, mixed> $filters */
    public function summary(array $filters): array
    {
        $query = Invoice::query()
            ->with(['buyer', 'customer'])
            ->whereNotIn('status', [InvoiceWorkflow::DRAFT, InvoiceWorkflow::CANCELLED]);
        $this->applyFilters($query, $filters);
        $invoices = $query->get();
        $today = now()->toDateString();
        $overdue = $invoices->filter(fn (Invoice $invoice): bool => (float) $invoice->due_amount > 0.0000001 && $invoice->due_date->toDateString() < $today);
        $partial = $invoices->filter(fn (Invoice $invoice): bool => $invoice->status === InvoiceWorkflow::PARTIALLY_PAID || ((float) $invoice->paid_amount > 0 && (float) $invoice->due_amount > 0));
        $parties = $invoices->groupBy(function (Invoice $invoice): string {
            return $invoice->buyer_id !== null ? 'buyer:'.$invoice->buyer_id : 'customer:'.$invoice->customer_id;
        })->map(function ($rows): array {
            /** @var Invoice $first */
            $first = $rows->first();
            $party = $first->buyer ?: $first->customer;

            return [
                'party_type' => $first->buyer_id !== null ? 'buyer' : 'customer',
                'party_id' => $first->buyer_id ?? $first->customer_id,
                'party_code' => $party?->code,
                'party_name' => $party?->name,
                'invoice_count' => $rows->count(),
                'total_invoiced' => $this->decimal($rows->sum(fn (Invoice $invoice): float => (float) $invoice->total_amount)),
                'total_paid' => $this->decimal($rows->sum(fn (Invoice $invoice): float => (float) $invoice->paid_amount)),
                'total_outstanding' => $this->decimal($rows->sum(fn (Invoice $invoice): float => (float) $invoice->due_amount)),
            ];
        })->values()->all();

        return [
            'total_invoiced' => $this->decimal($invoices->sum(fn (Invoice $invoice): float => (float) $invoice->total_amount)),
            'total_paid' => $this->decimal($invoices->sum(fn (Invoice $invoice): float => (float) $invoice->paid_amount)),
            'total_outstanding' => $this->decimal($invoices->sum(fn (Invoice $invoice): float => (float) $invoice->due_amount)),
            'overdue_amount' => $this->decimal($overdue->sum(fn (Invoice $invoice): float => (float) $invoice->due_amount)),
            'overdue_invoice_count' => $overdue->count(),
            'partially_paid_invoice_count' => $partial->count(),
            'invoice_count' => $invoices->count(),
            'party_breakdown' => $parties,
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['buyer_id', 'customer_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['invoice_date_from' => ['invoice_date', '>='], 'invoice_date_to' => ['invoice_date', '<='], 'due_date_from' => ['due_date', '>='], 'due_date_to' => ['due_date', '<=']] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
    }

    private function decimal(float|int|string $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
