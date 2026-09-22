@extends('pdf.layout', ['title' => $title, 'docNumber' => $reference, 'docDate' => \Illuminate\Support\Carbon::parse($statement['from'])->format('j M Y').' to '.\Illuminate\Support\Carbon::parse($statement['to'])->format('j M Y')])

@section('body')
    @php($customer = $statement['customer'])
    @php($ageing = $statement['ageing'])

    <table class="cols">
        <tr>
            <td>
                <h2>Statement for</h2>
                <div><strong>{{ $customer['name'] }}</strong></div>
                <div class="meta">Account {{ $customer['code'] }}</div>
                @if ($customer['address'])<div>{{ $customer['address'] }}</div>@endif
                @if ($customer['phone'])<div>{{ $customer['phone'] }}</div>@endif
                @if ($customer['email'])<div>{{ $customer['email'] }}</div>@endif
            </td>
            <td>
                <h2>Account terms</h2>
                <div>Payment terms: {{ $customer['payment_terms_days'] ? $customer['payment_terms_days'].' days' : 'Cash' }}</div>
                <div>Credit limit: KES {{ $money($customer['credit_limit']) }}</div>
                <div style="margin-top: 6px;">Balance due: <strong>KES {{ $money($statement['closing_balance']) }}</strong></div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr><th>Date</th><th>Transaction</th><th>Reference</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr>
        </thead>
        <tbody>
        @foreach ($statement['rows'] as $row)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('j M Y') }}</td>
                <td>{{ ucfirst(strtolower(str_replace('_', ' ', (string) $row['type']))) }}</td>
                <td>{{ $row['reference'] ?: '—' }}</td>
                <td class="num">{{ $row['type'] === 'OPENING_BALANCE' ? '' : $money($row['debit']) }}</td>
                <td class="num">{{ $row['type'] === 'OPENING_BALANCE' ? '' : $money($row['credit']) }}</td>
                <td class="num">{{ $money($row['balance']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Opening balance</td><td class="num">{{ $money($statement['opening_balance']) }}</td></tr>
        <tr><td>Invoiced in period</td><td class="num">{{ $money($statement['total_debit']) }}</td></tr>
        <tr><td>Paid and credited</td><td class="num">{{ $money($statement['total_credit']) }}</td></tr>
        <tr class="grand"><td>Closing balance (KES)</td><td class="num">{{ $money($statement['closing_balance']) }}</td></tr>
    </table>

    <h2 style="margin-top: 18px;">Age of the balance, as of today</h2>
    <table class="items" style="margin-top: 4px;">
        <thead>
        <tr><th class="num">Current</th><th class="num">1–30 days</th><th class="num">31–60 days</th><th class="num">61–90 days</th><th class="num">Over 90 days</th><th class="num">Total outstanding</th></tr>
        </thead>
        <tbody>
        <tr>
            <td class="num">{{ $money($ageing['current']) }}</td>
            <td class="num">{{ $money($ageing['d1_30']) }}</td>
            <td class="num">{{ $money($ageing['d31_60']) }}</td>
            <td class="num">{{ $money($ageing['d61_90']) }}</td>
            <td class="num">{{ $money($ageing['d90_plus']) }}</td>
            <td class="num"><strong>{{ $money($ageing['total']) }}</strong></td>
        </tr>
        </tbody>
    </table>

    <div class="note">
        Please check this statement against your records and tell us within 14 days if anything is wrong. Quote account {{ $customer['code'] }} with every payment.
    </div>

    @include('pdf.partials.issued-by')
@endsection
