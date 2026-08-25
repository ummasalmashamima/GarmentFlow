<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Requests\Reporting\AlertListRequest;
use App\Requests\Reporting\AlertStateRequest;
use App\Services\Reporting\AlertService;
use Illuminate\Http\JsonResponse;

final class AlertController extends Controller
{
    public function __construct(private readonly AlertService $alerts) {}

    public function index(AlertListRequest $request): JsonResponse
    {
        $paginator = $this->alerts->index($request->user(), $request->validated());
        $payload = $paginator->toArray();
        $payload['data'] = array_map(static fn (array $alert): array => [
            'id' => $alert['id'],
            'rule_code' => $alert['rule_code'],
            'severity' => $alert['severity'],
            'title' => $alert['title'],
            'description' => $alert['description'],
            'related_type' => $alert['related_type'],
            'related_id' => $alert['related_id'],
            'occurred_at' => $alert['occurred_at'],
            'resolved_at' => $alert['resolved_at'],
            'is_read' => $alert['user_read_at'] !== null,
        ], $payload['data']);

        return response()->json(['data' => $payload]);
    }

    public function refresh(AlertListRequest $request): JsonResponse
    {
        $alerts = $this->alerts->refresh($request->user());

        return response()->json(['data' => ['refreshed' => count($alerts), 'active' => count(array_filter($alerts, static fn (Alert $alert): bool => $alert->resolved_at === null))]]);
    }

    public function state(AlertStateRequest $request, Alert $alert): JsonResponse
    {
        if ($request->boolean('read')) {
            $this->alerts->markRead($request->user(), $alert);
        } else {
            $this->alerts->markUnread($request->user(), $alert);
        }

        return response()->json(['data' => ['id' => $alert->id, 'is_read' => $request->boolean('read')]]);
    }
}
