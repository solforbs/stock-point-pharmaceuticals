<?php

namespace App\Services\Reports;

/**
 * Shared shape for every report: typed columns, rows of decimal strings,
 * and totals for the columns that add up.
 */
trait FormatsReports
{
    /**
     * @return array{key: string, label: string, type: string}
     */
    protected function col(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    /**
     * @param  list<array{key: string, label: string, type: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $sumColumns
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    protected function result(array $columns, array $rows, array $sumColumns = []): array
    {
        $totals = ['rows' => count($rows)];
        foreach ($sumColumns as $key) {
            $isInt = true;
            $sum = '0.0000';
            foreach ($rows as $row) {
                $v = $row[$key] ?? 0;
                if (! is_int($v)) {
                    $isInt = false;
                }
                $sum = bcadd($sum, is_numeric($v) ? (string) $v : '0', 4);
            }
            $totals[$key] = $isInt ? (int) $sum : $sum;
        }

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    protected function d(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 4, '.', '');
    }

    protected function pct(string $part, string $whole): ?string
    {
        if (bccomp($whole, '0', 4) === 0) {
            return null;
        }

        return number_format((float) bcdiv(bcmul($part, '100', 6), $whole, 6), 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $row  with net_sales and cogs
     * @return array<string, mixed>
     */
    protected function withMargin(array $row): array
    {
        $row['gross_profit'] = bcsub((string) $row['net_sales'], (string) $row['cogs'], 4);
        $row['margin_pct'] = $this->pct($row['gross_profit'], (string) $row['net_sales']);

        return $row;
    }
}
