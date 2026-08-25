<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Models\SupplyPlan;
use App\Requests\Planning\SupplyPlanGenerateRequest;
use App\Requests\Planning\SupplyPlanPreviewRequest;
use App\Requests\Planning\SupplyPlanQueryRequest;
use App\Requests\Planning\SupplyPlanRecalculateRequest;
use App\Resources\Planning\SupplyPlanResource;
use App\Services\Planning\SupplyPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SupplyPlanController extends Controller
{
    public function __construct(private readonly SupplyPlanningService $supplyPlanningService) {}

    public function index(SupplyPlanQueryRequest $request): AnonymousResourceCollection
    {
        return SupplyPlanResource::collection($this->supplyPlanningService->paginate($request->validated()));
    }

    public function preview(SupplyPlanPreviewRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->supplyPlanningService->preview($request->validated())]);
    }

    public function generate(SupplyPlanGenerateRequest $request): AnonymousResourceCollection
    {
        return SupplyPlanResource::collection($this->supplyPlanningService->generate($request->validated(), $request->user()));
    }

    public function show(SupplyPlan $supplyPlan): SupplyPlanResource
    {
        return new SupplyPlanResource($this->supplyPlanningService->find($supplyPlan));
    }

    public function recalculate(SupplyPlanRecalculateRequest $request, SupplyPlan $supplyPlan): SupplyPlanResource
    {
        return new SupplyPlanResource($this->supplyPlanningService->recalculate($supplyPlan, $request->validated(), $request->user()));
    }
}
