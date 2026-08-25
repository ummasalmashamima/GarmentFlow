<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Requests\Orders\BuyerOrderItemRequest;
use App\Resources\Orders\BuyerOrderItemResource;
use App\Services\Orders\BuyerOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BuyerOrderItemController extends Controller
{
    public function __construct(private readonly BuyerOrderService $orderService) {}

    public function index(BuyerOrder $order): mixed
    {
        return BuyerOrderItemResource::collection($this->orderService->items($order));
    }

    public function store(BuyerOrderItemRequest $request, BuyerOrder $order): mixed
    {
        $item = $this->orderService->addItem($order, $request->validated(), $request->user());

        return (new BuyerOrderItemResource($item))->response()->setStatusCode(201);
    }

    public function update(BuyerOrderItemRequest $request, BuyerOrder $order, BuyerOrderItem $item): BuyerOrderItemResource
    {
        return new BuyerOrderItemResource($this->orderService->updateItem($order, $item, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, BuyerOrder $order, BuyerOrderItem $item): JsonResponse
    {
        $record = $this->orderService->deleteItem($order, $item, $request->user());

        return response()->json([
            'message' => 'Buyer Order item deleted successfully.',
            'data' => new BuyerOrderItemResource($record),
        ]);
    }
}
