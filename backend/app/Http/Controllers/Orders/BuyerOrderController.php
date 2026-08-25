<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\BuyerOrder;
use App\Requests\Orders\BuyerOrderApproveRequest;
use App\Requests\Orders\BuyerOrderConfirmRequest;
use App\Requests\Orders\BuyerOrderPreviewRequest;
use App\Requests\Orders\BuyerOrderQueryRequest;
use App\Requests\Orders\BuyerOrderRejectRequest;
use App\Requests\Orders\BuyerOrderRequest;
use App\Requests\Orders\BuyerOrderSubmitRequest;
use App\Requests\Orders\BuyerOrderTransitionRequest;
use App\Resources\Orders\BuyerOrderResource;
use App\Resources\Orders\OrderStatusHistoryResource;
use App\Services\Orders\BuyerOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BuyerOrderController extends Controller
{
    public function __construct(private readonly BuyerOrderService $orderService) {}

    public function index(BuyerOrderQueryRequest $request): mixed
    {
        return BuyerOrderResource::collection($this->orderService->paginate($request->validated()));
    }

    public function preview(BuyerOrderPreviewRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->orderService->previewTotals($request->validated('items'))]);
    }

    public function store(BuyerOrderRequest $request): mixed
    {
        $order = $this->orderService->create($request->validated(), $request->user());

        return (new BuyerOrderResource($order))->response()->setStatusCode(201);
    }

    public function show(BuyerOrder $order): BuyerOrderResource
    {
        return new BuyerOrderResource($this->orderService->find($order));
    }

    public function update(BuyerOrderRequest $request, BuyerOrder $order): BuyerOrderResource
    {
        return new BuyerOrderResource($this->orderService->update($order, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, BuyerOrder $order): JsonResponse
    {
        $record = $this->orderService->delete($order, $request->user());

        return response()->json([
            'message' => 'Buyer Order deleted successfully.',
            'data' => new BuyerOrderResource($record),
        ]);
    }

    public function submit(BuyerOrderSubmitRequest $request, BuyerOrder $order): BuyerOrderResource
    {
        return new BuyerOrderResource($this->orderService->submit($order, $request->validated('remarks'), $request->user()));
    }

    public function approve(BuyerOrderApproveRequest $request, BuyerOrder $order): BuyerOrderResource
    {
        return new BuyerOrderResource($this->orderService->approve($order, $request->validated('remarks'), $request->user()));
    }

    public function reject(BuyerOrderRejectRequest $request, BuyerOrder $order): BuyerOrderResource
    {
        return new BuyerOrderResource($this->orderService->reject($order, $request->validated('remarks'), $request->user()));
    }

    public function confirm(BuyerOrderConfirmRequest $request, BuyerOrder $order): BuyerOrderResource
    {
        return new BuyerOrderResource($this->orderService->confirm($order, $request->validated('remarks'), $request->user()));
    }

    public function transition(BuyerOrderTransitionRequest $request, BuyerOrder $order): BuyerOrderResource
    {
        return new BuyerOrderResource($this->orderService->transition($order, $request->validated('status'), $request->validated('remarks'), $request->user()));
    }

    public function history(Request $request, BuyerOrder $order): JsonResponse
    {
        $history = $this->orderService->history($order);

        return response()->json([
            'data' => [
                'status_history' => OrderStatusHistoryResource::collection($history['status_history']),
                'audit_logs' => $history['audit_logs'],
            ],
        ]);
    }
}
