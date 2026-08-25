<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Requests\Procurement\GoodsReceiptRequest;
use App\Requests\Procurement\ProcurementQueryRequest;
use App\Requests\Procurement\PurchaseOrderTransitionRequest;
use App\Resources\Procurement\GoodsReceiptResource;
use App\Services\Procurement\GoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class GoodsReceiptController extends Controller
{
    public function __construct(private readonly GoodsReceiptService $goodsReceiptService) {}

    public function index(ProcurementQueryRequest $request): AnonymousResourceCollection
    {
        return GoodsReceiptResource::collection($this->goodsReceiptService->paginate($request->validated()));
    }

    public function store(GoodsReceiptRequest $request): JsonResponse
    {
        return (new GoodsReceiptResource($this->goodsReceiptService->create($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        return new GoodsReceiptResource($this->goodsReceiptService->find($goodsReceipt));
    }

    public function receive(PurchaseOrderTransitionRequest $request, GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        return new GoodsReceiptResource($this->goodsReceiptService->receive($goodsReceipt, $request->validated('remarks'), $request->user()));
    }

    public function accept(PurchaseOrderTransitionRequest $request, GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        return new GoodsReceiptResource($this->goodsReceiptService->accept($goodsReceipt, $request->validated('remarks'), $request->user()));
    }

    public function post(PurchaseOrderTransitionRequest $request, GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        return new GoodsReceiptResource($this->goodsReceiptService->post($goodsReceipt, $request->validated('remarks'), $request->user()));
    }

    public function history(ProcurementQueryRequest $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        return response()->json(['data' => $this->goodsReceiptService->history($goodsReceipt)]);
    }
}
