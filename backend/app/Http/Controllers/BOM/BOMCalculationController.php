<?php

declare(strict_types=1);

namespace App\Http\Controllers\BOM;

use App\Http\Controllers\Controller;
use App\Models\BomHeader;
use App\Models\BomVersion;
use App\Requests\BOM\BOMCalculateRequest;
use App\Services\BOM\BOMCalculationService;
use App\Services\BOM\BOMService;
use Illuminate\Http\JsonResponse;

final class BOMCalculationController extends Controller
{
    public function __construct(
        private readonly BOMService $bomService,
        private readonly BOMCalculationService $calculationService,
    ) {}

    public function calculate(BOMCalculateRequest $request, BomHeader $bom, BomVersion $version): JsonResponse
    {
        $version = $this->bomService->findVersion($bom, $version);

        return response()->json([
            'data' => $this->calculationService->calculate($version, $request->validated('order_quantity')),
        ]);
    }
}
