<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Requests\Production\ProductionQueryRequest;
use App\Resources\Production\ProductionHistoryResource;
use App\Services\Production\ProductionHistoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductionHistoryController extends Controller
{
    public function __construct(private readonly ProductionHistoryService $historyService) {}

    public function index(ProductionQueryRequest $request): AnonymousResourceCollection
    {
        return ProductionHistoryResource::collection($this->historyService->paginate($request->validated()));
    }
}
