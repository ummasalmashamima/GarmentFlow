<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Requests\Finance\InvoiceActionRequest;
use App\Requests\Finance\InvoiceQueryRequest;
use App\Requests\Finance\InvoiceRequest;
use App\Requests\Finance\InvoiceStatusRequest;
use App\Requests\Finance\InvoiceUpdateRequest;
use App\Resources\Finance\FinanceHistoryResource;
use App\Resources\Finance\InvoiceResource;
use App\Services\Finance\InvoiceService;
use Illuminate\Http\JsonResponse;

final class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index(InvoiceQueryRequest $request): mixed
    {
        return InvoiceResource::collection($this->invoiceService->paginate($request->validated()));
    }

    public function eligibleSalesOrders(): JsonResponse
    {
        return response()->json(['data' => $this->invoiceService->eligibleSalesOrders()->map(fn ($order): array => [
            'id' => $order->id,
            'sales_order_number' => $order->sales_order_number,
            'status' => $order->status,
            'buyer' => $order->buyer ? ['id' => $order->buyer->id, 'code' => $order->buyer->code, 'name' => $order->buyer->name] : null,
            'customer' => $order->customer ? ['id' => $order->customer->id, 'code' => $order->customer->code, 'name' => $order->customer->name] : null,
            'warehouse' => $order->warehouse ? ['id' => $order->warehouse->id, 'code' => $order->warehouse->code, 'name' => $order->warehouse->name] : null,
            'delivered_quantity' => $order->delivered_quantity,
            'total_amount' => $order->total_amount,
            'items' => $order->items->map(fn ($item): array => [
                'id' => $item->id,
                'line_number' => $item->line_number,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'unit_id' => $item->unit_id,
                'product' => $item->product ? ['id' => $item->product->id, 'code' => $item->product->code, 'name' => $item->product->name] : null,
                'product_variant' => $item->productVariant ? ['id' => $item->productVariant->id, 'sku' => $item->productVariant->sku, 'variant_name' => $item->productVariant->variant_name] : null,
                'unit' => $item->unit ? ['id' => $item->unit->id, 'code' => $item->unit->code, 'name' => $item->unit->name, 'symbol' => $item->unit->symbol] : null,
                'delivered_quantity' => $item->delivered_quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount,
                'tax_amount' => $item->tax_amount,
            ])->values(),
        ])->values()]);
    }

    public function store(InvoiceRequest $request): mixed
    {
        return (new InvoiceResource($this->invoiceService->create($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoiceService->find($invoice));
    }

    public function update(InvoiceUpdateRequest $request, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoiceService->update($invoice, $request->validated(), $request->user()));
    }

    public function issue(InvoiceActionRequest $request, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoiceService->issue($invoice, $request->validated('remarks'), $request->user()));
    }

    public function cancel(InvoiceActionRequest $request, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoiceService->cancel($invoice, $request->validated('remarks'), $request->user()));
    }

    public function status(InvoiceStatusRequest $request, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoiceService->transition($invoice, $request->validated('status'), $request->validated('remarks'), $request->user()));
    }

    public function history(Invoice $invoice): JsonResponse
    {
        $history = $this->invoiceService->history($invoice);

        return response()->json(['data' => [
            'audit_logs' => FinanceHistoryResource::collection($history['audit_logs']),
        ]]);
    }
}
