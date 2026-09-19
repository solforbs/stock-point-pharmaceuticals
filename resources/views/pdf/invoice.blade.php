@extends('pdf.layout', ['title' => $title, 'docNumber' => $sale->doc_number, 'docDate' => $sale->posted_at?->format('j M Y, H:i')])

@section('body')
    <table class="cols">
        <tr>
            <td>
                <h2>Billed to</h2>
                <div><strong>{{ $sale->customer?->name ?? 'Walk-in customer' }}</strong></div>
                @if ($sale->customer?->code)<div class="meta">{{ $sale->customer->code }}</div>@endif
                @if ($sale->customer?->address)<div>{{ $sale->customer->address }}</div>@endif
                @if ($sale->customer?->phone)<div>{{ $sale->customer->phone }}</div>@endif
            </td>
            <td>
                <h2>Details</h2>
                <div>Sale mode: {{ $sale->sale_mode }}</div>
                <div>Served by: {{ $cashier }}</div>
                @if ($sale->terminal_id)<div>Till: {{ $sale->terminal_id }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th>#</th><th>Item</th><th class="num">Qty</th><th class="num">Unit price</th>
            <th class="num">Discount</th><th class="num">VAT</th><th class="num">Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($sale->lines as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    {{ $line->product?->name }}
                    @foreach ($line->batchAllocations as $allocation)
                        <div class="meta">Batch {{ $allocation->batch?->batch_number }} · expires {{ $allocation->batch?->expiry_date?->format('M Y') }}</div>
                    @endforeach
                </td>
                <td class="num">{{ $qty($line->qty) }} {{ $line->uom?->code }}</td>
                <td class="num">{{ $money($line->unit_price) }}</td>
                <td class="num">{{ $money($line->discount_amount) }}</td>
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
    </table>

    @if ($payments->isNotEmpty())
        <div class="note">
            Paid by
            @foreach ($payments as $payment){{ $payment->method }} {{ $money($payment->amount) }}@if (! $loop->last), @endif @endforeach
        </div>
    @endif

    @if ($sale->status === 'VOIDED')
        <div class="note warn">This sale has been voided and is not a valid demand for payment.</div>
    @endif

    <div class="note">Goods remain the property of {{ $letterhead['organisation'] }} until paid for in full.</div>
@endsection
