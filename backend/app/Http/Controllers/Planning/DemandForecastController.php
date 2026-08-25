<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Models\DemandForecast;
use App\Requests\Planning\DemandForecastActivateRequest;
use App\Requests\Planning\DemandForecastPreviewRequest;
use App\Requests\Planning\DemandForecastQueryRequest;
use App\Requests\Planning\DemandForecastRequest;
use App\Resources\Planning\DemandForecastResource;
use App\Services\Planning\ForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DemandForecastController extends Controller
{
    public function __construct(private readonly ForecastService $forecastService) {}

    public function index(DemandForecastQueryRequest $request): AnonymousResourceCollection
    {
        return DemandForecastResource::collection($this->forecastService->paginate($request->validated()));
    }

    public function preview(DemandForecastPreviewRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->forecastService->preview($request->validated())]);
    }

    public function store(DemandForecastRequest $request): mixed
    {
        return (new DemandForecastResource($this->forecastService->create($request->validated(), $request->user())))->response()->setStatusCode(201);
    }

    public function show(DemandForecast $forecast): DemandForecastResource
    {
        return new DemandForecastResource($this->forecastService->find($forecast));
    }

    public function update(DemandForecastRequest $request, DemandForecast $forecast): DemandForecastResource
    {
        return new DemandForecastResource($this->forecastService->update($forecast, $request->validated(), $request->user()));
    }

    public function activate(DemandForecastActivateRequest $request, DemandForecast $forecast): DemandForecastResource
    {
        return new DemandForecastResource($this->forecastService->activate($forecast, $request->user()));
    }
}
