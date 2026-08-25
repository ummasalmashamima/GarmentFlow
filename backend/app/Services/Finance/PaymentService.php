<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PaymentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly InvoiceWorkflow $invoiceWorkflow,
    ) {}

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Payment::query()->with(['invoice', 'buyer', 'customer', 'receiver']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('payment_method', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('invoice_number', 'like', "%{$search}%"))
                    ->orWhereHas('buyer', fn (Builder $party) => $party->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn (Builder $party) => $party->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }
        foreach (['status', 'invoice_id', 'buyer_id', 'customer_id', 'payment_method'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['payment_date_from' => ['payment_date', '>='], 'payment_date_to' => ['payment_date', '<=']] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'payment_number', 'payment_date', 'amount', 'status', 'payment_method'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(Payment $payment): Payment
    {
        return $payment->load(['invoice.salesOrder', 'buyer', 'customer', 'receiver']);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): Payment
    {
        return DB::transaction(function () use ($attributes, $actor): Payment {
            $existing = Payment::query()->where('idempotency_key', $attributes['idempotency_key'])->first();
            if ($existing !== null) {
                throw ValidationException::withMessages(['idempotency_key' => 'This payment submission has already been recorded.']);
            }
            $invoice = Invoice::query()->lockForUpdate()->findOrFail((int) $attributes['invoice_id']);
            $this->invoiceWorkflow->assertPayable($invoice);
            $amount = round((float) $attributes['amount'], 4);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }
            $due = round((float) $invoice->due_amount, 4);
            if ($amount > $due + 0.0000001) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the invoice due amount.']);
            }
            $payment = Payment::query()->create([
                'payment_number' => $this->generatePaymentNumber(),
                'invoice_id' => $invoice->getKey(),
                'buyer_id' => $invoice->buyer_id,
                'customer_id' => $invoice->customer_id,
                'payment_date' => $attributes['payment_date'],
                'amount' => $amount,
                'payment_method' => $attributes['payment_method'],
                'reference_number' => $attributes['reference_number'] ?? null,
                'idempotency_key' => $attributes['idempotency_key'],
                'status' => Payment::RECEIVED,
                'remarks' => $attributes['remarks'] ?? null,
                'received_by' => $actor->getKey(),
            ]);
            $this->auditLogService->record($actor, 'payments', $payment, 'created', null, $payment->attributesToArray());
            $this->refreshInvoicePaymentState($invoice, $actor, 'Payment '.$payment->payment_number.' recorded.');

            return $this->find($payment->refresh());
        });
    }

    public function void(Payment $payment, ?string $remarks, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $remarks, $actor): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
            if ($lockedPayment->status !== Payment::RECEIVED) {
                throw ValidationException::withMessages(['status' => 'Only received payments can be voided.']);
            }
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($lockedPayment->invoice_id);
            $oldValues = $lockedPayment->attributesToArray();
            $lockedPayment->forceFill(['status' => Payment::VOIDED, 'remarks' => $remarks ?: $lockedPayment->remarks])->save();
            $this->auditLogService->record($actor, 'payments', $lockedPayment, 'voided', $oldValues, $lockedPayment->attributesToArray());
            $this->refreshInvoicePaymentState($invoice, $actor, 'Payment '.$lockedPayment->payment_number.' voided.');

            return $this->find($lockedPayment->refresh());
        });
    }

    /** @return array{audit_logs: Collection<int, AuditLog>} */
    public function history(Payment $payment): array
    {
        return [
            'audit_logs' => AuditLog::query()
                ->with('user')
                ->where('module', 'payments')
                ->where('record_id', $payment->getKey())
                ->latest('id')
                ->get(),
        ];
    }

    private function refreshInvoicePaymentState(Invoice $invoice, User $actor, string $remarks): void
    {
        $paid = (float) Payment::query()
            ->where('invoice_id', $invoice->getKey())
            ->where('status', Payment::RECEIVED)
            ->sum('amount');
        $paid = round($paid, 4);
        $due = round(max((float) $invoice->total_amount - $paid, 0), 4);
        $oldValues = $invoice->attributesToArray();
        $newStatus = $invoice->status;
        if ($due <= 0.0000001) {
            $newStatus = InvoiceWorkflow::PAID;
        } elseif ($paid > 0) {
            $newStatus = InvoiceWorkflow::PARTIALLY_PAID;
        } elseif ($invoice->status === InvoiceWorkflow::PAID || $invoice->status === InvoiceWorkflow::PARTIALLY_PAID) {
            $newStatus = InvoiceWorkflow::ISSUED;
        }
        $invoice->forceFill(['paid_amount' => $paid, 'due_amount' => $due, 'status' => $newStatus])->save();
        $this->auditLogService->record($actor, 'invoices', $invoice, 'payment_state_updated', $oldValues, $invoice->attributesToArray());
        if ($newStatus !== $oldValues['status']) {
            $this->auditLogService->record($actor, 'invoices', $invoice, 'status_changed', ['status' => $oldValues['status']], ['status' => $newStatus, 'remarks' => $remarks]);
        }
    }

    private function generatePaymentNumber(): string
    {
        $prefix = 'PAY-'.now()->format('Ymd');
        $sequence = Payment::query()->where('payment_number', 'like', "{$prefix}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $prefix, $sequence++);
        } while (Payment::query()->where('payment_number', $candidate)->exists());

        return $candidate;
    }
}
