<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Requests\Production\ProductionQueryRequest;
use App\Resources\Production\MaterialConsumptionResource;
use App\Services\Production\MaterialConsumptionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MaterialConsumptionController extends Controller
{
    public function __construct(private readonly MaterialConsumptionService $consumptionService) {}

    public function index(ProductionQueryRequest $request): AnonymousResourceCollection
    {
        return MaterialConsumptionResource::collection($this->consumptionService->paginate($request->validated()));
    }
}
