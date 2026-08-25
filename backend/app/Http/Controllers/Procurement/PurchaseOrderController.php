<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Requests\Procurement\ProcurementQueryRequest;
use App\Requests\Procurement\PurchaseOrderRequest;
use App\Requests\Procurement\PurchaseOrderTransitionRequest;
use App\Resources\Procurement\PurchaseOrderResource;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrderService) {}

    public function index(ProcurementQueryRequest $request): AnonymousResourceCollection
    {
        return PurchaseOrderResource::collection($this->purchaseOrderService->paginate($request->validated()));
    }

    public function preview(PurchaseOrderRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->purchaseOrderService->preview($request->validated())]);
    }

    public function store(PurchaseOrderRequest $request): JsonResponse
    {
        $requisition = PurchaseRequisition::query()->findOrFail($request->validated('purchase_requisition_id'));

        return (new PurchaseOrderResource($this->purchaseOrderService->createFromRequisition($requisition, $request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchaseOrderService->find($purchaseOrder));
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchaseOrderService->update($purchaseOrder, $request->validated(), $request->user()));
    }

    public function submit(PurchaseOrderTransitionRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchaseOrderService->submit($purchaseOrder, $request->validated('remarks'), $request->user()));
    }

    public function approve(PurchaseOrderTransitionRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        abort_unless($request->user()->hasPermission('procurement.approve'), 403);

        return new PurchaseOrderResource($this->purchaseOrderService->approve($purchaseOrder, $request->validated('remarks'), $request->user()));
    }

    public function send(PurchaseOrderTransitionRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchaseOrderService->sendToSupplier($purchaseOrder, $request->validated('remarks'), $request->user()));
    }

    public function cancel(PurchaseOrderTransitionRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchaseOrderService->cancel($purchaseOrder, $request->validated('remarks'), $request->user()));
    }

    public function close(PurchaseOrderTransitionRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchaseOrderService->close($purchaseOrder, $request->validated('remarks'), $request->user()));
    }

    public function history(ProcurementQueryRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(['data' => $this->purchaseOrderService->history($purchaseOrder)]);
    }
}
