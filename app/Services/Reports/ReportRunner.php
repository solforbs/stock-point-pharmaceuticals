<?php

namespace App\Services\Reports;

use App\Models\AuditLog;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReportNotFoundException extends \RuntimeException {}

/**
 * Part 20.3 — every report on screen with filters, exportable, permission-
 * gated individually, every export written to the audit log.
 */
class ReportRunner
{
    /**
     * @return list<array<string, mixed>>
     */
    public function catalogueFor(User $user): array
    {
        $out = [];
        foreach (ReportCatalogue::all() as $key => $def) {
            if (! $this->allowed($user, $def['permissions'])) {
                continue;
            }
            $out[] = ['key' => $key, 'title' => $def['title'], 'group' => $def['group'], 'description' => $def['description'], 'filters' => array_merge(['from', 'to'], $def['filters'])];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function run(string $key, User $user, string $organisationId, string $branchId, array $filters = []): array
    {
        $def = ReportCatalogue::find($key);
        if (! $def) {
            throw new ReportNotFoundException("Unknown report {$key}.");
        }
        if (! $this->allowed($user, $def['permissions'])) {
            throw new HttpException(403, "Report {$key} needs ".implode(' and ', $def['permissions']).'.');
        }

        $ctx = ReportContext::make($organisationId, $branchId, $filters);
        $result = app($def['class'])->{$def['method']}($ctx);

        return ['key' => $key, 'title' => $def['title'], 'group' => $def['group'], 'generated_at' => now()->toIso8601String(), 'from' => $ctx->from, 'to' => $ctx->to, 'filters' => $filters] + $result;
    }

    /**
     * @param  array<string, mixed>  $report  as returned by run()
     */
    public function toCsv(array $report): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Could not open a temporary stream for the export.');
        }
        fputcsv($handle, array_map(fn ($c) => $c['label'], $report['columns']));
        foreach ($report['rows'] as $row) {
            fputcsv($handle, array_map(function ($c) use ($row) {
                $v = $row[$c['key']] ?? null;

                return is_bool($v) ? ($v ? 'yes' : 'no') : (is_array($v) || is_object($v) ? json_encode($v) : (string) $v);
            }, $report['columns']));
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        AuditLog::record('REPORT_EXPORTED', 'report', $report['key'], [
            'reference' => $report['title'],
            'after_json' => ['from' => $report['from'], 'to' => $report['to'], 'rows' => count($report['rows']), 'format' => 'csv'],
        ]);

        return $csv;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function allowed(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }
}
