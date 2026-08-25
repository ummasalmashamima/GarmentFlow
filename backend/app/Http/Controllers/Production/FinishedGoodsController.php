<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\FinishedGoods;
use App\Requests\Production\ProductionQueryRequest;
use App\Resources\Production\FinishedGoodsResource;
use App\Services\Production\FinishedGoodsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class FinishedGoodsController extends Controller
{
    public function __construct(private readonly FinishedGoodsService $finishedGoodsService) {}

    public function index(ProductionQueryRequest $request): AnonymousResourceCollection
    {
        return FinishedGoodsResource::collection($this->finishedGoodsService->paginate($request->validated()));
    }

    public function show(FinishedGoods $finishedGoods): FinishedGoodsResource
    {
        return new FinishedGoodsResource($this->finishedGoodsService->find($finishedGoods));
    }
}
