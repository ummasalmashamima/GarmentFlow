<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Requests\Inventory\InventoryQueryRequest;
use App\Requests\Inventory\StockTransferRequest;
use App\Resources\Inventory\InventoryTransactionResource;
use App\Resources\Inventory\StockTransferResource;
use App\Services\Inventory\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StockTransferController extends Controller
{
    public function __construct(private readonly StockTransferService $stockTransferService) {}

    public function index(InventoryQueryRequest $request): AnonymousResourceCollection
    {
        return StockTransferResource::collection($this->stockTransferService->paginate($request->validated()));
    }

    public function store(StockTransferRequest $request): JsonResponse
    {
        return (new StockTransferResource($this->stockTransferService->create($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(StockTransfer $stockTransfer): StockTransferResource
    {
        return new StockTransferResource($this->stockTransferService->find($stockTransfer));
    }

    public function history(InventoryQueryRequest $request, StockTransfer $stockTransfer): JsonResponse
    {
        $history = $this->stockTransferService->history($stockTransfer);
        $history['transactions'] = InventoryTransactionResource::collection($history['transactions'])->resolve($request);

        return response()->json(['data' => $history]);
    }
}
