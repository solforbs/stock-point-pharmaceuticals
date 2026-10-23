@php
    // Word cannot show an embedded (data URI) image, so its copy carries the name in place of the logo.
    $forWord = $forWord ?? false;
    $letterheadForDoc = $forWord ? array_merge($letterhead, ['logo' => null]) : $letterhead;
    $format = function ($value, string $type) {
        if ($value === null || $value === '') {
            return '';
        }
        return match ($type) {
            'money' => number_format((float) $value, 2),
            'qty' => rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.'),
            'int' => number_format((float) $value, 0),
            'percent', 'pct' => number_format((float) $value, 1).'%',
            default => (string) $value,
        };
    };
    $numeric = fn (string $type) => in_array($type, ['money', 'qty', 'int', 'percent', 'pct'], true);
@endphp
@extends('pdf.layout', ['title' => $title, 'docNumber' => ($report['rows'] ? count($report['rows']).' rows' : 'No rows'), 'docDate' => $date, 'letterhead' => $letterheadForDoc])

@section('body')
    @if (! empty($report['assumptions']['formula']))
        <p class="meta" style="margin: 0 0 8px;">{{ $report['assumptions']['formula'] }}</p>
    @endif

    <table class="items">
        <thead>
        <tr>
            <th style="text-align: left;">No.</th>
            @foreach ($report['columns'] as $column)
                <th style="{{ $numeric($column['type']) ? 'text-align: right;' : '' }}">{{ $column['label'] }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse ($report['rows'] as $row)
            <tr>
                <td style="text-align: left;">{{ $loop->iteration }}</td>
                @foreach ($report['columns'] as $column)
                    <td style="{{ $numeric($column['type']) ? 'text-align: right;' : '' }}">{{ $format($row[$column['key']] ?? null, $column['type']) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($report['columns']) + 1 }}">Nothing to report for this selection.</td></tr>
        @endforelse
        </tbody>
        @php($sums = array_diff_key($report['totals'] ?? [], ['rows' => true]))
        @if ($sums !== [])
            <tfoot>
            <tr style="font-weight: bold; background: #f3f4f6;">
                <td>Totals</td>
                @foreach ($report['columns'] as $column)
                    <td style="{{ $numeric($column['type']) ? 'text-align: right;' : '' }}">{{ array_key_exists($column['key'], $sums) ? $format($sums[$column['key']], $column['type']) : '' }}</td>
                @endforeach
            </tr>
            </tfoot>
        @endif
    </table>
@endsection
