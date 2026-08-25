<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Requests\Finance\FinanceSummaryQueryRequest;
use App\Services\Finance\AccountsPayableService;
use App\Services\Finance\AccountsReceivableService;
use App\Services\Finance\ProfitSummaryService;
use Illuminate\Http\JsonResponse;

final class FinanceSummaryController extends Controller
{
    public function __construct(
        private readonly AccountsReceivableService $receivableService,
        private readonly AccountsPayableService $payableService,
        private readonly ProfitSummaryService $profitService,
    ) {}

    public function receivables(FinanceSummaryQueryRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->receivableService->summary($request->validated())]);
    }

    public function payables(FinanceSummaryQueryRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->payableService->summary($request->validated())]);
    }

    public function profit(FinanceSummaryQueryRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->profitService->summary($request->validated())]);
    }
}
