<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Models\Delivery;
use Illuminate\Validation\ValidationException;

final class DeliveryWorkflow
{
    public const CREATED = 'created';

    public const READY_FOR_SHIPMENT = 'ready_for_shipment';

    public const SHIPPED = 'shipped';

    public const IN_TRANSIT = 'in_transit';

    public const OUT_FOR_DELIVERY = 'out_for_delivery';

    public const DELIVERED = 'delivered';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const FAILED = 'failed';

    public const RETURNED = 'returned';

    /** @return array<int, string> */
    public function statuses(): array
    {
        return [
            self::CREATED,
            self::READY_FOR_SHIPMENT,
            self::SHIPPED,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
            self::COMPLETED,
            self::CANCELLED,
            self::FAILED,
            self::RETURNED,
        ];
    }

    /** @return array<string, string> */
    public function transitions(): array
    {
        return [
            self::CREATED => self::READY_FOR_SHIPMENT,
            self::READY_FOR_SHIPMENT => self::SHIPPED,
            self::SHIPPED => self::IN_TRANSIT,
            self::IN_TRANSIT => self::OUT_FOR_DELIVERY,
            self::OUT_FOR_DELIVERY => self::DELIVERED,
            self::DELIVERED => self::COMPLETED,
        ];
    }

    public function assertMutable(Delivery $delivery): void
    {
        if (in_array($delivery->status, [self::CANCELLED, self::COMPLETED, self::FAILED, self::RETURNED], true)) {
            throw ValidationException::withMessages(['status' => 'Terminal deliveries cannot be changed.']);
        }
    }

    public function assertDispatchable(Delivery $delivery): void
    {
        $this->assertMutable($delivery);
        if (! in_array($delivery->status, [self::CREATED, self::READY_FOR_SHIPMENT], true)) {
            throw ValidationException::withMessages(['status' => 'Only created or ready-for-shipment deliveries can be dispatched.']);
        }
        if ((float) $delivery->dispatched_quantity > 0) {
            throw ValidationException::withMessages(['status' => 'This delivery has already been dispatched.']);
        }
    }

    public function assertTransition(Delivery $delivery, string $newStatus): void
    {
        if ($newStatus === self::CANCELLED && in_array($delivery->status, [self::CREATED, self::READY_FOR_SHIPMENT], true)) {
            return;
        }
        if ($newStatus === self::FAILED && in_array($delivery->status, [self::SHIPPED, self::IN_TRANSIT, self::OUT_FOR_DELIVERY], true)) {
            return;
        }
        if ($newStatus === self::RETURNED && in_array($delivery->status, [self::DELIVERED, self::COMPLETED], true)) {
            return;
        }
        if (($this->transitions()[$delivery->status] ?? null) !== $newStatus) {
            throw ValidationException::withMessages([
                'status' => "Delivery cannot transition from {$delivery->status} to {$newStatus}.",
            ]);
        }
    }

    public function assertCompletable(Delivery $delivery): void
    {
        $this->assertMutable($delivery);
        if ($delivery->status !== self::DELIVERED) {
            throw ValidationException::withMessages(['status' => 'Only delivered shipments can be completed.']);
        }
    }
}
