<?php

declare(strict_types=1);

namespace App\Http\Controllers\BOM;

use App\Http\Controllers\Controller;
use App\Models\BomHeader;
use App\Models\BomVersion;
use App\Requests\BOM\BOMVersionRequest;
use App\Resources\BOM\BomVersionResource;
use App\Services\BOM\BOMService;
use Illuminate\Http\Request;

final class BOMVersionController extends Controller
{
    public function __construct(private readonly BOMService $bomService) {}

    public function index(BomHeader $bom): mixed
    {
        return BomVersionResource::collection($this->bomService->versions($bom));
    }

    public function store(BOMVersionRequest $request, BomHeader $bom): mixed
    {
        $version = $this->bomService->createVersion($bom, $request->validated(), $request->user());

        return (new BomVersionResource($version))->response()->setStatusCode(201);
    }

    public function show(BomHeader $bom, BomVersion $version): BomVersionResource
    {
        return new BomVersionResource($this->bomService->findVersion($bom, $version));
    }

    public function update(BOMVersionRequest $request, BomHeader $bom, BomVersion $version): BomVersionResource
    {
        return new BomVersionResource($this->bomService->updateVersion($bom, $version, $request->validated(), $request->user()));
    }

    public function activate(Request $request, BomHeader $bom, BomVersion $version): BomVersionResource
    {
        return new BomVersionResource($this->bomService->activateVersion($bom, $version, $request->user()));
    }

    public function deactivate(Request $request, BomHeader $bom, BomVersion $version): BomVersionResource
    {
        return new BomVersionResource($this->bomService->deactivateVersion($bom, $version, $request->user()));
    }
}
