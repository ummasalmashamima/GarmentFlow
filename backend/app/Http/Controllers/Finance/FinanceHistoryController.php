<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Requests\Finance\FinanceHistoryQueryRequest;
use App\Resources\Finance\FinanceHistoryResource;
use App\Services\Finance\FinanceHistoryService;

final class FinanceHistoryController extends Controller
{
    public function __construct(private readonly FinanceHistoryService $historyService) {}

    public function index(FinanceHistoryQueryRequest $request): mixed
    {
        return FinanceHistoryResource::collection($this->historyService->paginate($request->validated()));
    }
}
