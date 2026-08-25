<?php

declare(strict_types=1);

namespace App\Http\Controllers\BOM;

use App\Http\Controllers\Controller;
use App\Models\BomHeader;
use App\Models\BomItem;
use App\Models\BomVersion;
use App\Requests\BOM\BOMItemRequest;
use App\Resources\BOM\BomItemResource;
use App\Services\BOM\BOMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BOMItemController extends Controller
{
    public function __construct(private readonly BOMService $bomService) {}

    public function index(BomHeader $bom, BomVersion $version): mixed
    {
        return BomItemResource::collection($this->bomService->items($bom, $version));
    }

    public function store(BOMItemRequest $request, BomHeader $bom, BomVersion $version): mixed
    {
        $item = $this->bomService->createItem($bom, $version, $request->validated(), $request->user());

        return (new BomItemResource($item))->response()->setStatusCode(201);
    }

    public function update(BOMItemRequest $request, BomHeader $bom, BomVersion $version, BomItem $item): BomItemResource
    {
        return new BomItemResource($this->bomService->updateItem($bom, $version, $item, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, BomHeader $bom, BomVersion $version, BomItem $item): JsonResponse
    {
        $record = $this->bomService->deleteItem($bom, $version, $item, $request->user());

        return response()->json([
            'message' => 'BOM item deleted successfully.',
            'data' => new BomItemResource($record),
        ]);
    }
}
