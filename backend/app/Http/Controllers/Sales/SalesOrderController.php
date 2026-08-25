<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Requests\Sales\SalesOrderCancelRequest;
use App\Requests\Sales\SalesOrderConfirmRequest;
use App\Requests\Sales\SalesOrderPreviewRequest;
use App\Requests\Sales\SalesOrderQueryRequest;
use App\Requests\Sales\SalesOrderRequest;
use App\Requests\Sales\SalesOrderStatusRequest;
use App\Requests\Sales\SalesOrderSubmitRequest;
use App\Resources\Sales\SalesOrderResource;
use App\Resources\Sales\SalesOrderStatusHistoryResource;
use App\Services\Sales\SalesOrderService;
use Illuminate\Http\JsonResponse;

final class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $salesOrderService) {}

    public function index(SalesOrderQueryRequest $request): mixed
    {
        return SalesOrderResource::collection($this->salesOrderService->paginate($request->validated()));
    }

    public function preview(SalesOrderPreviewRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->salesOrderService->previewTotals(
            $request->validated('items'),
            $request->validated('order_discount_amount', 0),
            $request->validated('order_tax_amount', 0),
        )]);
    }

    public function store(SalesOrderRequest $request): mixed
    {
        $order = $this->salesOrderService->create($request->validated(), $request->user());

        return (new SalesOrderResource($order))->response()->setStatusCode(201);
    }

    public function show(SalesOrder $salesOrder): SalesOrderResource
    {
        return new SalesOrderResource($this->salesOrderService->find($salesOrder));
    }

    public function update(SalesOrderRequest $request, SalesOrder $salesOrder): SalesOrderResource
    {
        return new SalesOrderResource($this->salesOrderService->update($salesOrder, $request->validated(), $request->user()));
    }

    public function submit(SalesOrderSubmitRequest $request, SalesOrder $salesOrder): SalesOrderResource
    {
        return new SalesOrderResource($this->salesOrderService->submit($salesOrder, $request->validated('remarks'), $request->user()));
    }

    public function confirm(SalesOrderConfirmRequest $request, SalesOrder $salesOrder): SalesOrderResource
    {
        return new SalesOrderResource($this->salesOrderService->confirm($salesOrder, $request->validated('remarks'), $request->user()));
    }

    public function cancel(SalesOrderCancelRequest $request, SalesOrder $salesOrder): SalesOrderResource
    {
        return new SalesOrderResource($this->salesOrderService->cancel($salesOrder, $request->validated('remarks'), $request->user()));
    }

    public function status(SalesOrderStatusRequest $request, SalesOrder $salesOrder): SalesOrderResource
    {
        return new SalesOrderResource($this->salesOrderService->transition($salesOrder, $request->validated('status'), $request->validated('remarks'), $request->user()));
    }

    public function availability(SalesOrder $salesOrder): JsonResponse
    {
        return response()->json(['data' => $this->salesOrderService->availability($salesOrder)]);
    }

    public function statusHistory(SalesOrder $salesOrder): JsonResponse
    {
        $history = $this->salesOrderService->history($salesOrder);

        return response()->json([
            'data' => [
                'status_history' => SalesOrderStatusHistoryResource::collection($history['status_history']),
                'audit_logs' => $history['audit_logs'],
            ],
        ]);
    }
}
