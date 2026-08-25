<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\ProductionPlan;
use App\Requests\Production\ProductionPlanApprovalRequest;
use App\Requests\Production\ProductionPlanRequest;
use App\Requests\Production\ProductionPlanStatusRequest;
use App\Requests\Production\ProductionQueryRequest;
use App\Resources\Production\ProductionPlanResource;
use App\Services\Production\ProductionPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductionPlanController extends Controller
{
    public function __construct(private readonly ProductionPlanService $planService) {}

    public function index(ProductionQueryRequest $request): AnonymousResourceCollection
    {
        return ProductionPlanResource::collection($this->planService->paginate($request->validated()));
    }

    public function store(ProductionPlanRequest $request): JsonResponse
    {
        return (new ProductionPlanResource($this->planService->create($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProductionPlan $productionPlan): ProductionPlanResource
    {
        return new ProductionPlanResource($this->planService->find($productionPlan));
    }

    public function update(ProductionPlanRequest $request, ProductionPlan $productionPlan): ProductionPlanResource
    {
        return new ProductionPlanResource($this->planService->update($productionPlan, $request->validated(), $request->user()));
    }

    public function approve(ProductionPlanApprovalRequest $request, ProductionPlan $productionPlan): ProductionPlanResource
    {
        return new ProductionPlanResource($this->planService->approve($productionPlan, $request->user()));
    }

    public function transition(ProductionPlanStatusRequest $request, ProductionPlan $productionPlan): ProductionPlanResource
    {
        return new ProductionPlanResource($this->planService->transition($productionPlan, $request->validated()['status'], $request->user()));
    }
}
