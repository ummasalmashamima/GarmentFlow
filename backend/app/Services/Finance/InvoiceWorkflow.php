<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

final class InvoiceWorkflow
{
    public const DRAFT = 'draft';

    public const ISSUED = 'issued';

    public const PARTIALLY_PAID = 'partially_paid';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    public const OVERDUE = 'overdue';

    public function transitions(): array
    {
        return [
            self::DRAFT => [self::ISSUED, self::CANCELLED],
            self::ISSUED => [self::PARTIALLY_PAID, self::PAID, self::OVERDUE, self::CANCELLED],
            self::PARTIALLY_PAID => [self::PAID, self::OVERDUE, self::CANCELLED],
            self::OVERDUE => [self::PARTIALLY_PAID, self::PAID, self::CANCELLED],
            self::PAID => [],
            self::CANCELLED => [],
        ];
    }

    public function assertTransition(Invoice $invoice, string $newStatus): void
    {
        if (! in_array($newStatus, $this->transitions()[$invoice->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Invoice status cannot transition from {$invoice->status} to {$newStatus}.",
            ]);
        }
    }

    public function assertIssuable(Invoice $invoice): void
    {
        if ($invoice->status !== self::DRAFT) {
            throw ValidationException::withMessages(['status' => 'Only draft invoices can be issued.']);
        }
    }

    public function assertPayable(Invoice $invoice): void
    {
        if (in_array($invoice->status, [self::DRAFT, self::CANCELLED, self::PAID], true)) {
            throw ValidationException::withMessages(['status' => 'This invoice cannot receive payments in its current status.']);
        }
    }
}
