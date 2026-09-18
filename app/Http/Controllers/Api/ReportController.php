<?php

namespace App\Http\Controllers\Api;

use App\Services\Reports\ReportNotFoundException;
use App\Services\Reports\ReportRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Part 20 — the report catalogue, on screen and exported. */
class ReportController extends ApiController
{
    public function catalogue(Request $request, ReportRunner $runner): JsonResponse
    {
        return response()->json(['data' => $runner->catalogueFor($request->user())]);
    }

    public function run(Request $request, string $report, ReportRunner $runner): JsonResponse|Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'format' => ['nullable', 'in:json,csv'],
            'customer_id' => ['nullable', 'uuid'],
            'product_id' => ['nullable', 'uuid'],
            'store_id' => ['nullable', 'uuid'],
            'category_id' => ['nullable', 'uuid'],
            'threshold_pct' => ['nullable', 'numeric', 'min:0'],
            'threshold' => ['nullable', 'numeric', 'min:0'],
            'dead_days' => ['nullable', 'integer', 'min:1'],
            'open_hour' => ['nullable', 'integer', 'between:0,23'],
            'close_hour' => ['nullable', 'integer', 'between:1,24'],
        ]);
        $format = $filters['format'] ?? 'json';
        unset($filters['format']);

        try {
            $result = $runner->run($report, $request->user(), $this->organisationId($request), $this->branchId($request), array_filter($filters, fn ($v) => $v !== null));
        } catch (ReportNotFoundException $e) {
            return $this->error('NOT_FOUND', $e->getMessage(), 404);
        }

        if ($format === 'csv') {
            return response($runner->toCsv($result), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.str_replace('.', '-', $report).'-'.$result['from'].'-'.$result['to'].'.csv"',
            ]);
        }

        return response()->json($result);
    }
}
