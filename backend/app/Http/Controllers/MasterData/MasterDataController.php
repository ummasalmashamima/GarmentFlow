<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Requests\MasterData\MasterDataQueryRequest;
use App\Requests\MasterData\MasterDataRequest;
use App\Resources\MasterData\MasterDataResource;
use App\Services\MasterData\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MasterDataController extends Controller
{
    public function __construct(private readonly MasterDataService $masterDataService) {}

    public function index(MasterDataQueryRequest $request): mixed
    {
        return MasterDataResource::collection(
            $this->masterDataService->paginate((string) $request->route('resource'), $request->validated()),
        );
    }

    public function store(MasterDataRequest $request): mixed
    {
        $record = $this->masterDataService->create(
            (string) $request->route('resource'),
            $request->validated(),
            $request->user(),
        );

        return (new MasterDataResource($record))->response()->setStatusCode(201);
    }

    public function show(Request $request): MasterDataResource
    {
        return new MasterDataResource(
            $this->masterDataService->find((string) $request->route('resource'), $request->route('id')),
        );
    }

    public function update(MasterDataRequest $request): MasterDataResource
    {
        return new MasterDataResource(
            $this->masterDataService->update(
                (string) $request->route('resource'),
                $request->route('id'),
                $request->validated(),
                $request->user(),
            ),
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $result = $this->masterDataService->deleteOrDeactivate(
            (string) $request->route('resource'),
            $request->route('id'),
            $request->user(),
        );

        return response()->json([
            'message' => $result['deactivated']
                ? 'Record deactivated because it is referenced by other records.'
                : 'Record deleted successfully.',
            'data' => new MasterDataResource($result['record']),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->masterDataService->options((string) $request->route('resource')),
        ]);
    }
}
