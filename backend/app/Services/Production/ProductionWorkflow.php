<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\ProductionOrder;
use App\Models\ProductionPlan;
use Illuminate\Validation\ValidationException;

final class ProductionWorkflow
{
    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const SCHEDULED = 'scheduled';

    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /** @return array<int, string> */
    public function planStatuses(): array
    {
        return [self::DRAFT, self::APPROVED, self::SCHEDULED, self::IN_PROGRESS, self::COMPLETED, self::CANCELLED];
    }

    /** @return array<int, string> */
    public function orderStatuses(): array
    {
        return [self::SCHEDULED, self::IN_PROGRESS, self::COMPLETED, self::CANCELLED];
    }

    /** @return array<string, string> */
    public function planTransitions(): array
    {
        return [
            self::DRAFT => self::APPROVED,
            self::APPROVED => self::SCHEDULED,
            self::SCHEDULED => self::IN_PROGRESS,
            self::IN_PROGRESS => self::COMPLETED,
        ];
    }

    /** @return array<string, string> */
    public function orderTransitions(): array
    {
        return [
            self::SCHEDULED => self::IN_PROGRESS,
            self::IN_PROGRESS => self::COMPLETED,
        ];
    }

    public function assertPlanTransition(ProductionPlan $plan, string $newStatus): void
    {
        if (in_array($newStatus, [self::CANCELLED], true) && in_array($plan->status, [self::DRAFT, self::APPROVED, self::SCHEDULED, self::IN_PROGRESS], true)) {
            return;
        }

        if (($this->planTransitions()[$plan->status] ?? null) !== $newStatus) {
            throw ValidationException::withMessages([
                'status' => "Production Plan cannot transition from {$plan->status} to {$newStatus}.",
            ]);
        }
    }

    public function assertOrderTransition(ProductionOrder $order, string $newStatus): void
    {
        if ($newStatus === self::CANCELLED && in_array($order->status, [self::SCHEDULED, self::IN_PROGRESS], true)) {
            return;
        }

        if (($this->orderTransitions()[$order->status] ?? null) !== $newStatus) {
            throw ValidationException::withMessages([
                'status' => "Production Order cannot transition from {$order->status} to {$newStatus}.",
            ]);
        }
    }
}
