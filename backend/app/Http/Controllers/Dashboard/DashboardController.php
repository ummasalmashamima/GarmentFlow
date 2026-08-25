<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Requests\Reporting\DashboardQueryRequest;
use App\Services\Reporting\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboards) {}

    public function show(DashboardQueryRequest $request, string $dashboard): JsonResponse
    {
        $permission = 'dashboard.'.$dashboard.'.view';
        abort_unless($request->user()->tokenCan($permission) && Gate::forUser($request->user())->allows($permission), 403);

        return response()->json(['data' => $this->dashboards->show($dashboard, $request->validated())]);
    }
}
