<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use App\Requests\Production\MaterialConsumptionRequest;
use App\Requests\Production\ProductionCompletionRequest;
use App\Requests\Production\ProductionOrderRequest;
use App\Requests\Production\ProductionOrderStatusRequest;
use App\Requests\Production\ProductionProgressRequest;
use App\Requests\Production\ProductionQueryRequest;
use App\Requests\Production\ProductionStartRequest;
use App\Resources\Production\MaterialConsumptionResource;
use App\Resources\Production\ProductionOrderResource;
use App\Resources\Production\ProductionProgressResource;
use App\Services\Production\MaterialConsumptionService;
use App\Services\Production\ProductionOrderService;
use App\Services\Production\ProductionProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $orderService,
        private readonly MaterialConsumptionService $consumptionService,
        private readonly ProductionProgressService $progressService,
    ) {}

    public function index(ProductionQueryRequest $request): AnonymousResourceCollection
    {
        return ProductionOrderResource::collection($this->orderService->paginate($request->validated()));
    }

    public function store(ProductionOrderRequest $request): JsonResponse
    {
        return (new ProductionOrderResource($this->orderService->create($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProductionOrder $productionOrder): ProductionOrderResource
    {
        return new ProductionOrderResource($this->orderService->find($productionOrder));
    }

    public function availability(ProductionOrder $productionOrder): JsonResponse
    {
        return response()->json(['data' => $this->orderService->availability($productionOrder)]);
    }

    public function start(ProductionStartRequest $request, ProductionOrder $productionOrder): ProductionOrderResource
    {
        return new ProductionOrderResource($this->orderService->start($productionOrder, $request->user()));
    }

    public function status(ProductionOrderStatusRequest $request, ProductionOrder $productionOrder): ProductionOrderResource
    {
        return new ProductionOrderResource($this->orderService->transition($productionOrder, $request->validated()['status'], $request->user()));
    }

    public function consume(MaterialConsumptionRequest $request, ProductionOrder $productionOrder): JsonResponse
    {
        return (new MaterialConsumptionResource($this->consumptionService->consume($productionOrder, $request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function progress(ProductionProgressRequest $request, ProductionOrder $productionOrder): JsonResponse
    {
        return (new ProductionProgressResource($this->progressService->record($productionOrder, $request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function complete(ProductionCompletionRequest $request, ProductionOrder $productionOrder): ProductionOrderResource
    {
        return new ProductionOrderResource($this->orderService->complete($productionOrder, $request->validated(), $request->user()));
    }

    public function consumptions(ProductionQueryRequest $request): AnonymousResourceCollection
    {
        return MaterialConsumptionResource::collection($this->consumptionService->paginate($request->validated()));
    }

    public function progressHistory(ProductionQueryRequest $request): AnonymousResourceCollection
    {
        return ProductionProgressResource::collection($this->progressService->paginate($request->validated()));
    }
}
