<?php

declare(strict_types=1);

namespace App\Requests\Finance;

use Illuminate\Validation\Rule;

final class FinanceRules
{
    public static function invoiceCreate(): array
    {
        return [
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.sales_order_item_id' => ['required', 'integer', 'exists:sales_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'gte:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public static function invoiceUpdate(): array
    {
        return [
            'invoice_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:invoice_date'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public static function invoiceQuery(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['draft', 'issued', 'partially_paid', 'paid', 'cancelled', 'overdue'])],
            'buyer_id' => ['nullable', 'integer', 'exists:buyers,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sales_order_id' => ['nullable', 'integer', 'exists:sales_orders,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'invoice_date_from' => ['nullable', 'date'],
            'invoice_date_to' => ['nullable', 'date', 'after_or_equal:invoice_date_from'],
            'due_date_from' => ['nullable', 'date'],
            'due_date_to' => ['nullable', 'date', 'after_or_equal:due_date_from'],
            'sort' => ['nullable', Rule::in(['id', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'paid_amount', 'due_amount', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    public static function status(): array
    {
        return [
            'status' => ['required', Rule::in(['issued', 'cancelled', 'partially_paid', 'paid', 'overdue'])],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public static function paymentCreate(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'string', 'max:50'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['required', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public static function paymentQuery(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['received', 'voided'])],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'buyer_id' => ['nullable', 'integer', 'exists:buyers,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_date_from' => ['nullable', 'date'],
            'payment_date_to' => ['nullable', 'date', 'after_or_equal:payment_date_from'],
            'sort' => ['nullable', Rule::in(['id', 'payment_number', 'payment_date', 'amount', 'status', 'payment_method'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    public static function summary(): array
    {
        return [
            'buyer_id' => ['nullable', 'integer', 'exists:buyers,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'invoice_date_from' => ['nullable', 'date'],
            'invoice_date_to' => ['nullable', 'date', 'after_or_equal:invoice_date_from'],
            'due_date_from' => ['nullable', 'date'],
            'due_date_to' => ['nullable', 'date', 'after_or_equal:due_date_from'],
            'po_date_from' => ['nullable', 'date'],
            'po_date_to' => ['nullable', 'date', 'after_or_equal:po_date_from'],
        ];
    }

    public static function history(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', Rule::in(['invoices', 'invoice-items', 'payments'])],
            'action' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'sort' => ['nullable', Rule::in(['id', 'module', 'action', 'created_at', 'record_id'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
