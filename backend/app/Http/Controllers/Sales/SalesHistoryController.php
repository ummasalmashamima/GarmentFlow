<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Requests\Sales\SalesHistoryQueryRequest;
use App\Resources\Sales\SalesHistoryResource;
use App\Services\Sales\SalesHistoryService;

final class SalesHistoryController extends Controller
{
    public function __construct(private readonly SalesHistoryService $salesHistoryService) {}

    public function index(SalesHistoryQueryRequest $request): mixed
    {
        return SalesHistoryResource::collection($this->salesHistoryService->paginate($request->validated()));
    }
}
