<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DeliveryTrackingHistory;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ShipmentTrackingService
{
    public function __construct(private readonly AuditLogService $auditLogService, private readonly DeliveryWorkflow $workflow) {}

    /** @param array<string, mixed> $attributes */
    public function update(Delivery $delivery, array $attributes, User $actor): Delivery
    {
        return DB::transaction(function () use ($delivery, $attributes, $actor): Delivery {
            $locked = Delivery::query()->lockForUpdate()->findOrFail($delivery->getKey());
            $this->workflow->assertMutable($locked);
            if (($attributes['status'] ?? null) !== null && $attributes['status'] !== $locked->status) {
                throw ValidationException::withMessages(['status' => 'Use the Delivery status endpoint for status transitions.']);
            }
            $previousStatus = $locked->status;
            $trackingUpdates = [];
            foreach (['carrier_name', 'tracking_number'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $trackingUpdates[$field] = $attributes[$field];
                }
            }
            $locked->forceFill($trackingUpdates)->save();
            $this->record($locked, $previousStatus, $locked->status, $attributes, $actor);

            return $locked->fresh(['salesOrder.buyer', 'salesOrder.customer', 'warehouse', 'items.product', 'items.productVariant', 'items.unit', 'items.inventoryTransaction', 'trackingHistories.changer']);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function record(Delivery $delivery, ?string $previousStatus, string $newStatus, array $attributes, User $actor): DeliveryTrackingHistory
    {
        $history = $delivery->trackingHistories()->create([
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'carrier_name' => array_key_exists('carrier_name', $attributes) ? $attributes['carrier_name'] : $delivery->carrier_name,
            'tracking_number' => array_key_exists('tracking_number', $attributes) ? $attributes['tracking_number'] : $delivery->tracking_number,
            'location' => array_key_exists('location', $attributes) ? $attributes['location'] : null,
            'changed_by' => $actor->getKey(),
            'remarks' => $attributes['remarks'] ?? null,
        ]);
        $this->auditLogService->record($actor, 'deliveries', $delivery, 'tracking_updated', null, [
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'carrier_name' => $history->carrier_name,
            'tracking_number' => $history->tracking_number,
            'location' => $history->location,
            'remarks' => $history->remarks,
        ]);

        return $history;
    }

    /** @return array{tracking_history: Collection<int, DeliveryTrackingHistory>} */
    public function history(Delivery $delivery): array
    {
        return ['tracking_history' => $delivery->trackingHistories()->with('changer')->latest('id')->get()];
    }
}
