<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Models\MrpRun;
use App\Requests\Planning\MaterialRequirementPreviewRequest;
use App\Requests\Planning\MaterialRequirementQueryRequest;
use App\Requests\Planning\MaterialRequirementRequest;
use App\Resources\Planning\MrpRunResource;
use App\Services\Planning\MaterialRequirementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MaterialRequirementController extends Controller
{
    public function __construct(private readonly MaterialRequirementService $materialRequirementService) {}

    public function index(MaterialRequirementQueryRequest $request): AnonymousResourceCollection
    {
        return MrpRunResource::collection($this->materialRequirementService->paginate($request->validated()));
    }

    public function preview(MaterialRequirementPreviewRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->materialRequirementService->preview($request->validated())]);
    }

    public function generate(MaterialRequirementRequest $request): mixed
    {
        return (new MrpRunResource($this->materialRequirementService->generate($request->validated(), $request->user())))->response()->setStatusCode(201);
    }

    public function show(MrpRun $run): MrpRunResource
    {
        return new MrpRunResource($this->materialRequirementService->find($run));
    }
}
