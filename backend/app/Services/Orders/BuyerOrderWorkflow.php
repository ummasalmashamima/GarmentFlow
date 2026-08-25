<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\BuyerOrder;
use Illuminate\Validation\ValidationException;

final class BuyerOrderWorkflow
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const PENDING_APPROVAL = 'pending_approval';

    public const CONFIRMED = 'confirmed';

    public const PLANNING = 'planning';

    public const IN_PRODUCTION = 'in_production';

    public const READY = 'ready';

    public const SHIPPED = 'shipped';

    public const DELIVERED = 'delivered';

    public const COMPLETED = 'completed';

    /**
     * @return array<int, string>
     */
    public function statuses(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::PENDING_APPROVAL,
            self::CONFIRMED,
            self::PLANNING,
            self::IN_PRODUCTION,
            self::READY,
            self::SHIPPED,
            self::DELIVERED,
            self::COMPLETED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function downstreamTransitions(): array
    {
        return [
            self::CONFIRMED => self::PLANNING,
            self::PLANNING => self::IN_PRODUCTION,
            self::IN_PRODUCTION => self::READY,
            self::READY => self::SHIPPED,
            self::SHIPPED => self::DELIVERED,
            self::DELIVERED => self::COMPLETED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function firmDemandStatuses(): array
    {
        return [
            self::CONFIRMED,
            self::PLANNING,
            self::IN_PRODUCTION,
            self::READY,
            self::SHIPPED,
            self::DELIVERED,
            self::COMPLETED,
        ];
    }

    public function isDraft(BuyerOrder $order): bool
    {
        return $order->status === self::DRAFT;
    }

    public function assertDraft(BuyerOrder $order): void
    {
        if (! $this->isDraft($order)) {
            throw ValidationException::withMessages([
                'status' => 'Only draft orders can be edited.',
            ]);
        }
    }

    public function assertTransition(BuyerOrder $order, string $newStatus): void
    {
        $expected = $this->downstreamTransitions()[$order->status] ?? null;

        if ($expected !== $newStatus) {
            throw ValidationException::withMessages([
                'status' => "Order status cannot transition from {$order->status} to {$newStatus}.",
            ]);
        }
    }
}
