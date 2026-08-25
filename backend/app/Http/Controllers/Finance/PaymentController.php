<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Requests\Finance\PaymentQueryRequest;
use App\Requests\Finance\PaymentRequest;
use App\Requests\Finance\PaymentStatusRequest;
use App\Resources\Finance\FinanceHistoryResource;
use App\Resources\Finance\PaymentResource;
use App\Services\Finance\PaymentService;
use Illuminate\Http\JsonResponse;

final class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(PaymentQueryRequest $request): mixed
    {
        return PaymentResource::collection($this->paymentService->paginate($request->validated()));
    }

    public function store(PaymentRequest $request): mixed
    {
        return (new PaymentResource($this->paymentService->create($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Payment $payment): PaymentResource
    {
        return new PaymentResource($this->paymentService->find($payment));
    }

    public function status(PaymentStatusRequest $request, Payment $payment): PaymentResource
    {
        return new PaymentResource($this->paymentService->void($payment, $request->validated('remarks'), $request->user()));
    }

    public function history(Payment $payment): JsonResponse
    {
        return response()->json(['data' => [
            'audit_logs' => FinanceHistoryResource::collection($this->paymentService->history($payment)['audit_logs']),
        ]]);
    }
}
