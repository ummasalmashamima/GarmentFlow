<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Requests\Production\ProductionQueryRequest;
use App\Resources\Production\ProductionProgressResource;
use App\Services\Production\ProductionProgressService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductionProgressController extends Controller
{
    public function __construct(private readonly ProductionProgressService $progressService) {}

    public function index(ProductionQueryRequest $request): AnonymousResourceCollection
    {
        return ProductionProgressResource::collection($this->progressService->paginate($request->validated()));
    }
}
