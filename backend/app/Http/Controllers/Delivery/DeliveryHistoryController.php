<?php

declare(strict_types=1);

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Requests\Delivery\DeliveryHistoryQueryRequest;
use App\Resources\Delivery\DeliveryHistoryResource;
use App\Services\Delivery\DeliveryHistoryService;

final class DeliveryHistoryController extends Controller
{
    public function __construct(private readonly DeliveryHistoryService $historyService) {}

    public function index(DeliveryHistoryQueryRequest $request): mixed
    {
        return DeliveryHistoryResource::collection($this->historyService->list($request->validated()));
    }
}
