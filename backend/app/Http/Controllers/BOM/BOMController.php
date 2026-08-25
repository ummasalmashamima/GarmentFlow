<?php

declare(strict_types=1);

namespace App\Http\Controllers\BOM;

use App\Http\Controllers\Controller;
use App\Models\BomHeader;
use App\Requests\BOM\BOMActivationRequest;
use App\Requests\BOM\BOMHeaderRequest;
use App\Requests\BOM\BOMQueryRequest;
use App\Resources\BOM\BomResource;
use App\Services\BOM\BOMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BOMController extends Controller
{
    public function __construct(private readonly BOMService $bomService) {}

    public function index(BOMQueryRequest $request): mixed
    {
        return BomResource::collection($this->bomService->paginate($request->validated()));
    }

    public function store(BOMHeaderRequest $request): mixed
    {
        $bom = $this->bomService->create($request->validated(), $request->user());

        return (new BomResource($bom))->response()->setStatusCode(201);
    }

    public function show(BomHeader $bom): BomResource
    {
        return new BomResource($this->bomService->find($bom));
    }

    public function update(BOMHeaderRequest $request, BomHeader $bom): BomResource
    {
        return new BomResource($this->bomService->update($bom, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, BomHeader $bom): JsonResponse
    {
        $record = $this->bomService->delete($bom, $request->user());

        return response()->json([
            'message' => 'BOM deleted successfully.',
            'data' => new BomResource($record),
        ]);
    }

    public function activate(BOMActivationRequest $request, BomHeader $bom): BomResource
    {
        return new BomResource($this->bomService->activate($bom, $request->validated('version_id'), $request->user()));
    }

    public function deactivate(Request $request, BomHeader $bom): BomResource
    {
        return new BomResource($this->bomService->deactivate($bom, $request->user()));
    }
}
