<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\SalesOrder;
use Illuminate\Validation\ValidationException;

final class SalesOrderWorkflow
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const CONFIRMED = 'confirmed';

    public const READY_FOR_DELIVERY = 'ready_for_delivery';

    public const DELIVERED = 'delivered';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /** @return array<int, string> */
    public function statuses(): array
    {
        return [self::DRAFT, self::SUBMITTED, self::CONFIRMED, self::READY_FOR_DELIVERY, self::DELIVERED, self::COMPLETED, self::CANCELLED];
    }

    /** @return array<string, string> */
    public function transitions(): array
    {
        return [
            self::DRAFT => self::SUBMITTED,
            self::SUBMITTED => self::CONFIRMED,
            self::CONFIRMED => self::READY_FOR_DELIVERY,
            self::READY_FOR_DELIVERY => self::DELIVERED,
            self::DELIVERED => self::COMPLETED,
        ];
    }

    public function assertDraft(SalesOrder $order): void
    {
        if ($order->status !== self::DRAFT) {
            throw ValidationException::withMessages(['status' => 'Only draft Sales Orders can be edited.']);
        }
    }

    public function assertTransition(SalesOrder $order, string $newStatus): void
    {
        if ($newStatus === self::CANCELLED && in_array($order->status, [self::DRAFT, self::SUBMITTED, self::CONFIRMED], true)) {
            return;
        }

        if (($this->transitions()[$order->status] ?? null) !== $newStatus) {
            throw ValidationException::withMessages([
                'status' => "Sales Order cannot transition from {$order->status} to {$newStatus}.",
            ]);
        }
    }

    public function assertCancellable(SalesOrder $order): void
    {
        $this->assertTransition($order, self::CANCELLED);
    }
}
