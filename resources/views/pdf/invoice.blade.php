@extends('pdf.layout', ['title' => $title, 'docNumber' => $sale->doc_number, 'docDate' => $sale->posted_at?->format('j M Y, H:i')])

@section('body')
    <table class="cols">
        <tr>
            <td>
                <h2>{{ $title === 'Cash sale invoice' ? 'Sold to' : 'Billed to' }}</h2>
                <div><strong>{{ $sale->customer?->name ?? 'Walk-in customer' }}</strong></div>
                @if ($sale->customer?->code)<div class="meta">{{ $sale->customer->code }}</div>@endif
                @if ($sale->customer?->address)<div>{{ $sale->customer->address }}</div>@endif
                @if ($sale->customer?->phone)<div>{{ $sale->customer->phone }}</div>@endif            </td>
            <td>
                <h2>Details</h2>
                <div>Sale mode: {{ $sale->sale_mode }}</div>
                <div>Served by: {{ $cashier }}</div>
                <div>Payment: {{ $title === 'Cash sale invoice' ? 'Paid in full at the counter' : ($title === 'Tax invoice' ? 'On account' : '—') }}</div>
                @if ($sale->terminal_id)<div>Till: {{ $sale->terminal_id }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th>#</th><th>Item</th><th class="num">Qty</th><th class="num">Unit price</th>
            <th class="num">Discount</th><th class="num">VAT rate</th><th class="num">VAT</th><th class="num">Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($sale->lines as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    {{ $line->product?->name }}@if ($line->product?->strength && ! str_contains((string) $line->product?->name, (string) $line->product?->strength)) {{ $line->product->strength }}@endif
                    @if ($line->is_bonus)<strong>(free)</strong>@endif
                    <div class="meta">{{ $line->product?->code }}@if ($line->product?->generic_name) · {{ $line->product->generic_name }}@endif</div>
                    @foreach ($line->batchAllocations as $allocation)
                        <div class="meta">Batch {{ $allocation->batch?->batch_number }} · expires {{ $allocation->batch?->expiry_date?->format('M Y') }}</div>
                    @endforeach
                </td>
                <td class="num">{{ $qty($line->qty) }} {{ $line->uom?->code }}</td>
                <td class="num">{{ $money($line->unit_price) }}</td>
                <td class="num">{{ $money($line->discount_amount) }}</td>
                <td class="num">{{ str_contains(strtoupper((string) $line->taxCode?->code), 'ZERO') ? 'Z 0%' : (str_contains(strtoupper((string) $line->taxCode?->code), 'EX') ? 'E' : rtrim(rtrim(number_format((float) $line->tax_rate, 2), '0'), '.').'%') }}</td>
                <td class="num">{{ $money($line->tax_amount) }}</td>
                <td class="num">{{ $money($line->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $money($sale->subtotal) }}</td></tr>
        <tr><td>Discount</td><td class="num">{{ $money($sale->discount_total) }}</td></tr>
        <tr><td>VAT</td><td class="num">{{ $money($sale->tax_total) }}</td></tr>
        <tr class="grand"><td>Total (KES)</td><td class="num">{{ $money($sale->grand_total) }}</td></tr>
        @foreach ($payments as $payment)
            <tr><td>{{ $payment->method }}@if ($payment->reference) {{ $payment->reference }}@endif</td><td class="num">{{ $money($payment->amount) }}</td></tr>
        @endforeach
        @if ((float) $balanceDue > 0)
            <tr><td><strong>Balance due</strong></td><td class="num"><strong>{{ $money($balanceDue) }}</strong></td></tr>
        @endif
    </table>

    @if ($amountInWords)
        <div class="note"><strong>Amount in words:</strong> {{ $amountInWords }}</div>
    @endif

    @if (count($vatAnalysis) > 0)
        <table class="items" style="width: 60%; margin-top: 14px;">
            <thead><tr><th>VAT analysis</th><th class="num">Net amount</th><th class="num">VAT</th></tr></thead>
            <tbody>
            @foreach ($vatAnalysis as $row)
                <tr><td>{{ $row['label'] }}</td><td class="num">{{ $money($row['net']) }}</td><td class="num">{{ $money($row['vat']) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if ($sale->etims_invoice_number ?? null)
        <div class="note">eTIMS invoice: {{ $sale->etims_invoice_number }}</div>
    @endif

    @if ($sale->status === 'VOIDED')
        <div class="note warn">This sale has been voided and is not a valid demand for payment.</div>
    @endif

    <div class="note">Goods remain the property of {{ $letterhead['organisation'] }} until paid for in full.</div>

    @include('pdf.partials.issued-by')
@endsection
