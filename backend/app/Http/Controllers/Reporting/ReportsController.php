<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Requests\Reporting\ReportQueryRequest;
use App\Services\Reporting\ReportsService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportsController extends Controller
{
    public function __construct(private readonly ReportsService $reports) {}

    public function index(ReportQueryRequest $request, string $report): JsonResponse
    {
        return response()->json(['data' => $this->reports->run($report, $request->validated())]);
    }

    public function export(ReportQueryRequest $request, string $report): StreamedResponse
    {
        $filters = [...$request->validated(), 'per_page' => 100];
        $rows = [];
        $page = 1;
        do {
            $pageResult = $this->reports->run($report, [...$filters, 'page' => $page]);
            $pageRows = $pageResult['rows']['data'] ?? [];
            $rows = [...$rows, ...$pageRows];
            $lastPage = (int) ($pageResult['rows']['last_page'] ?? $page);
            $page++;
        } while ($page <= $lastPage && $page <= 1000);

        $headers = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                if (! in_array($key, $headers, true)) {
                    $headers[] = $key;
                }
            }
        }
        if ($headers === []) {
            $headers = ['message'];
        }
        $filename = 'garmentflow-'.$report.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows, $headers): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $headers);
            if ($rows === []) {
                fputcsv($handle, ['No matching records']);
            } else {
                foreach ($rows as $row) {
                    fputcsv($handle, array_map(static fn ($key) => is_array($row[$key] ?? null) ? json_encode($row[$key], JSON_THROW_ON_ERROR) : ($row[$key] ?? ''), $headers));
                }
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
