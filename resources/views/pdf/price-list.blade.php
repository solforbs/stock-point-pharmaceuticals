@extends('pdf.layout', ['title' => $title, 'docNumber' => 'Prices in KES', 'docDate' => $date, 'hideKraPin' => true])

@section('body')
    {{-- dompdf lays one long table out far more slowly than many short ones, so the
         list is printed in blocks of 50 rows with fixed column widths that line up;
         only the first block carries the header. Every row is numbered (No.). --}}
    @php
        $share = ['code' => 14, 'name' => 52, 'brand_name' => 22, 'price' => 14];
        $total = array_sum(array_intersect_key($share, array_flip($columns)));
        $widths = array_map(fn ($c) => round($share[$c] * 93 / max($total, 1), 2), $columns);
    @endphp
    @foreach (array_chunk($rows, 50) as $block)
        <table class="items" style="table-layout: fixed;{{ $loop->first ? '' : ' margin-top: 0;' }}">
            @if ($loop->first)
                <thead>
                <tr>
                    <th style="width: 7%; text-align: left;">No.</th>
                    @foreach ($columns as $i => $column)
                        <th style="width: {{ $widths[$i] }}%;{{ $column === 'price' ? ' text-align: right;' : '' }}">{{ $labels[$column] }}</th>
                    @endforeach
                </tr>
                </thead>
            @endif
            <tbody>
            @foreach ($block as $row)
                <tr>
                    {{-- Widths on the cells themselves: dompdf does not honour <col> widths. --}}
                    <td style="width: 7%; text-align: left;">{{ ($loop->parent->index * 50) + $loop->iteration }}</td>
                    @foreach ($columns as $i => $column)
                        @if ($column === 'price')
                            <td class="num" style="width: {{ $widths[$i] }}%;">{{ $row['price'] === null || $row['price'] === '' ? '—' : number_format((float) $row['price'], 2) }}</td>
                        @else
                            <td style="width: {{ $widths[$i] }}%;">{{ $row[$column] ?? '' }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    @endforeach

    <p class="meta" style="margin-top: 10px;">{{ count($rows) }} products in stock. Prices in KES, subject to availability.</p>

    @php($hasMpesa = $payment['mpesa_paybill'] || $payment['mpesa_till'])
    @if ($hasMpesa || count($payment['banks']) > 0)
        <div style="margin-top: 16px; border: 1.5px solid #1e3a8a; padding: 10px 12px; page-break-inside: avoid;">
            <div style="font-size: 12px; font-weight: bold; color: #1e3a8a; margin-bottom: 6px;">HOW TO PAY</div>
            <table class="cols">
                <tr>
                    @if ($hasMpesa)
                        <td>
                            <div style="font-weight: bold; color: #15803d; font-size: 11px;">LIPA NA M-PESA</div>
                            @if ($payment['note'])<div class="meta">{{ $payment['note'] }}</div>@endif
                            @if ($payment['mpesa_paybill'])
                                <div style="margin-top: 4px;">Business number (paybill): <strong style="font-size: 12px;">{{ $payment['mpesa_paybill'] }}</strong></div>
                                @if ($payment['mpesa_account'])<div>Account number: <strong style="font-size: 12px;">{{ $payment['mpesa_account'] }}</strong></div>@endif
                            @endif
                            @if ($payment['mpesa_till'])<div>Buy Goods till: <strong style="font-size: 12px;">{{ $payment['mpesa_till'] }}</strong></div>@endif
                            <div>Account name: <strong>{{ $payment['account_name'] }}</strong></div>
                        </td>
                    @endif
                    @if (count($payment['banks']) > 0)
                        <td>
                            <div style="font-weight: bold; color: #1e3a8a; font-size: 11px;">BANK TRANSFER</div>
                            @foreach ($payment['banks'] as $bank)
                                <div style="margin-top: 4px;">
                                    <strong>{{ $bank['bank_name'] }}</strong>@if ($bank['branch_name']), {{ $bank['branch_name'] }}@endif<br>
                                    A/C {{ $bank['account_number'] }} · {{ $bank['account_name'] ?: $payment['account_name'] }}
                                </div>
                            @endforeach
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    @endif
@endsection
