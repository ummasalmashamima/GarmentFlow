<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Requests\Inventory\InventoryQueryRequest;
use App\Requests\Inventory\StockMovementRequest;
use App\Resources\Inventory\InventoryBalanceResource;
use App\Resources\Inventory\InventoryTransactionResource;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function index(InventoryQueryRequest $request): AnonymousResourceCollection
    {
        return InventoryBalanceResource::collection($this->inventoryService->paginate($request->validated()));
    }

    public function show(InventoryBalance $inventoryBalance): InventoryBalanceResource
    {
        return new InventoryBalanceResource($this->inventoryService->find($inventoryBalance));
    }

    public function summary(InventoryQueryRequest $request): array
    {
        return ['data' => $this->inventoryService->summary($request->validated())];
    }

    public function warehouseStock(InventoryQueryRequest $request, Warehouse $warehouse): AnonymousResourceCollection
    {
        abort_unless($warehouse->status === 'active', 404);

        return InventoryBalanceResource::collection($this->inventoryService->warehouseStock($warehouse->getKey(), $request->validated()));
    }

    public function locationStock(InventoryQueryRequest $request, WarehouseLocation $warehouseLocation): AnonymousResourceCollection
    {
        abort_unless($warehouseLocation->status === 'active', 404);

        return InventoryBalanceResource::collection($this->inventoryService->locationStock($warehouseLocation->getKey(), $request->validated()));
    }

    public function history(InventoryQueryRequest $request): AnonymousResourceCollection
    {
        return InventoryTransactionResource::collection($this->inventoryService->transactionHistory($request->validated()));
    }

    public function stockIn(StockMovementRequest $request): JsonResponse
    {
        return (new InventoryTransactionResource($this->inventoryService->stockIn($request->validated(), $request->user())['transaction']))
            ->response()
            ->setStatusCode(201);
    }

    public function stockOut(StockMovementRequest $request): JsonResponse
    {
        return (new InventoryTransactionResource($this->inventoryService->stockOut($request->validated(), $request->user())['transaction']))
            ->response()
            ->setStatusCode(201);
    }
}
