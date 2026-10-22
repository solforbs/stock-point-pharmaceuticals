@extends('pdf.layout', ['title' => $title, 'docNumber' => 'Prices in KES', 'docDate' => $date, 'hideKraPin' => true])

@section('body')
    <table class="items">
        <thead>
        <tr>
            @foreach ($columns as $column)
                <th @class(['num' => $column === 'price'])>{{ $labels[$column] }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                @foreach ($columns as $column)
                    @if ($column === 'price')
                        <td class="num">{{ $row['price'] === null || $row['price'] === '' ? '—' : number_format((float) $row['price'], 2) }}</td>
                    @else
                        <td>{{ $row[$column] ?? '' }}</td>
                    @endif
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

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
