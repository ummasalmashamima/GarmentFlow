<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequisition;
use App\Requests\Procurement\ProcurementQueryRequest;
use App\Requests\Procurement\PurchaseOrderRequest;
use App\Requests\Procurement\PurchaseRequisitionRequest;
use App\Requests\Procurement\PurchaseRequisitionTransitionRequest;
use App\Resources\Procurement\PurchaseOrderResource;
use App\Resources\Procurement\PurchaseRequisitionResource;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseRequisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PurchaseRequisitionController extends Controller
{
    public function __construct(
        private readonly PurchaseRequisitionService $requisitionService,
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {}

    public function index(ProcurementQueryRequest $request): AnonymousResourceCollection
    {
        return PurchaseRequisitionResource::collection($this->requisitionService->paginate($request->validated()));
    }

    public function store(PurchaseRequisitionRequest $request): JsonResponse
    {
        return (new PurchaseRequisitionResource($this->requisitionService->create($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PurchaseRequisition $requisition): PurchaseRequisitionResource
    {
        return new PurchaseRequisitionResource($this->requisitionService->find($requisition));
    }

    public function update(PurchaseRequisitionRequest $request, PurchaseRequisition $requisition): PurchaseRequisitionResource
    {
        return new PurchaseRequisitionResource($this->requisitionService->update($requisition, $request->validated(), $request->user()));
    }

    public function submit(PurchaseRequisitionTransitionRequest $request, PurchaseRequisition $requisition): PurchaseRequisitionResource
    {
        return new PurchaseRequisitionResource($this->requisitionService->submit($requisition, $request->validated('remarks'), $request->user()));
    }

    public function approve(PurchaseRequisitionTransitionRequest $request, PurchaseRequisition $requisition): PurchaseRequisitionResource
    {
        abort_unless($request->user()->hasPermission('procurement.approve'), 403);

        return new PurchaseRequisitionResource($this->requisitionService->approve($requisition, $request->validated('remarks'), $request->user()));
    }

    public function reject(PurchaseRequisitionTransitionRequest $request, PurchaseRequisition $requisition): PurchaseRequisitionResource
    {
        abort_unless($request->user()->hasPermission('procurement.approve'), 403);

        return new PurchaseRequisitionResource($this->requisitionService->reject($requisition, $request->validated('remarks'), $request->user()));
    }

    public function convert(PurchaseOrderRequest $request, PurchaseRequisition $requisition): JsonResponse
    {
        return (new PurchaseOrderResource($this->requisitionService->convert($requisition, $request->validated(), $request->user(), $this->purchaseOrderService)))
            ->response()
            ->setStatusCode(201);
    }

    public function history(ProcurementQueryRequest $request, PurchaseRequisition $requisition): JsonResponse
    {
        return response()->json(['data' => $this->requisitionService->history($requisition)]);
    }
}
