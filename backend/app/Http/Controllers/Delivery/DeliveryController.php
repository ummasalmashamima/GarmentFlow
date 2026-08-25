<?php

declare(strict_types=1);

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Requests\Delivery\DeliveryCompleteRequest;
use App\Requests\Delivery\DeliveryDispatchRequest;
use App\Requests\Delivery\DeliveryQueryRequest;
use App\Requests\Delivery\DeliveryRequest;
use App\Requests\Delivery\DeliveryStatusRequest;
use App\Requests\Delivery\DeliveryTrackingRequest;
use App\Requests\Delivery\DeliveryUpdateRequest;
use App\Resources\Delivery\DeliveryHistoryResource;
use App\Resources\Delivery\DeliveryResource;
use App\Resources\Delivery\DeliveryTrackingHistoryResource;
use App\Services\Delivery\DeliveryService;
use App\Services\Delivery\ShipmentTrackingService;
use Illuminate\Http\JsonResponse;

final class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveryService, private readonly ShipmentTrackingService $trackingService) {}

    public function index(DeliveryQueryRequest $request): mixed
    {
        return DeliveryResource::collection($this->deliveryService->list($request->validated()));
    }

    public function store(DeliveryRequest $request): mixed
    {
        $delivery = $this->deliveryService->create($request->validated(), $request->user());

        return (new DeliveryResource($delivery))->response()->setStatusCode(201);
    }

    public function show(Delivery $delivery): DeliveryResource
    {
        return new DeliveryResource($this->deliveryService->find($delivery));
    }

    public function update(DeliveryUpdateRequest $request, Delivery $delivery): DeliveryResource
    {
        return new DeliveryResource($this->deliveryService->update($delivery, $request->validated(), $request->user()));
    }

    public function dispatch(DeliveryDispatchRequest $request, Delivery $delivery): DeliveryResource
    {
        return new DeliveryResource($this->deliveryService->dispatch($delivery, $request->user()));
    }

    public function status(DeliveryStatusRequest $request, Delivery $delivery): DeliveryResource
    {
        return new DeliveryResource($this->deliveryService->transition($delivery, $request->validated('status'), $request->validated('remarks'), $request->user()));
    }

    public function tracking(DeliveryTrackingRequest $request, Delivery $delivery): DeliveryResource
    {
        return new DeliveryResource($this->trackingService->update($delivery, $request->validated(), $request->user()));
    }

    public function complete(DeliveryCompleteRequest $request, Delivery $delivery): DeliveryResource
    {
        return new DeliveryResource($this->deliveryService->complete($delivery, $request->validated('remarks'), $request->user()));
    }

    public function history(Delivery $delivery): JsonResponse
    {
        $history = $this->deliveryService->history($delivery);

        return response()->json([
            'data' => [
                'tracking_history' => DeliveryTrackingHistoryResource::collection($history['tracking_history']),
                'audit_logs' => DeliveryHistoryResource::collection($history['audit_logs']),
            ],
        ]);
    }

    public function trackingHistory(Delivery $delivery): JsonResponse
    {
        return response()->json([
            'data' => DeliveryTrackingHistoryResource::collection($this->trackingService->history($delivery)['tracking_history']),
        ]);
    }
}
