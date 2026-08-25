<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Requests\Inventory\InventoryQueryRequest;
use App\Requests\Inventory\StockAdjustmentRequest;
use App\Resources\Inventory\InventoryTransactionResource;
use App\Resources\Inventory\StockAdjustmentResource;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StockAdjustmentController extends Controller
{
    public function __construct(private readonly StockAdjustmentService $stockAdjustmentService) {}

    public function index(InventoryQueryRequest $request): AnonymousResourceCollection
    {
        return StockAdjustmentResource::collection($this->stockAdjustmentService->paginate($request->validated()));
    }

    public function store(StockAdjustmentRequest $request): JsonResponse
    {
        return (new StockAdjustmentResource($this->stockAdjustmentService->create($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(StockAdjustment $stockAdjustment): StockAdjustmentResource
    {
        return new StockAdjustmentResource($this->stockAdjustmentService->find($stockAdjustment));
    }

    public function history(InventoryQueryRequest $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        $history = $this->stockAdjustmentService->history($stockAdjustment);
        $history['transactions'] = InventoryTransactionResource::collection($history['transactions'])->resolve($request);

        return response()->json(['data' => $history]);
    }
}
