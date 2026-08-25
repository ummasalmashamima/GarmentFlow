<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Requests\Procurement\ProcurementQueryRequest;
use App\Resources\Procurement\ProcurementHistoryResource;
use App\Services\Procurement\ProcurementHistoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProcurementHistoryController extends Controller
{
    public function __construct(private readonly ProcurementHistoryService $historyService) {}

    public function index(ProcurementQueryRequest $request): AnonymousResourceCollection
    {
        return ProcurementHistoryResource::collection($this->historyService->paginate($request->validated()));
    }
}
