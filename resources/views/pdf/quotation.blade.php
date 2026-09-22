@extends('pdf.layout', ['title' => $title, 'docNumber' => $quotation->doc_number, 'docDate' => $quotation->created_at?->format('j M Y, H:i')])

@section('body')
    <table class="cols">
        <tr>
            <td>
                <h2>Quotation for</h2>
                <div><strong>{{ $quotation->customer?->name ?? 'Direct customer' }}</strong></div>
                @if ($quotation->customer?->code)<div class="meta">Code: {{ $quotation->customer->code }}</div>@endif
                @if ($quotation->customer?->address)<div>{{ $quotation->customer->address }}</div>@endif
                @if ($quotation->customer?->phone)<div>Tel: {{ $quotation->customer->phone }}</div>@endif
                @if ($quotation->customer?->kra_pin)<div class="meta">PIN: {{ $quotation->customer->kra_pin }}</div>@endif
            </td>
            <td>
                <h2>Terms & Validity</h2>
                <div>Status: <strong>{{ $quotation->status }}</strong></div>
                @if ($quotation->valid_until)<div>Valid until: <strong>{{ $quotation->valid_until->format('j M Y') }}</strong></div>@endif
                @if ($quotation->sale_mode)<div>Channel: {{ $quotation->sale_mode }}</div>@endif
                @if ($quotation->notes)<div class="meta" style="margin-top: 4px;">{{ $quotation->notes }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th>#</th>
            <th>Item description</th>
            <th>UOM</th>
            <th class="num">Qty</th>
            <th class="num">Unit price</th>
            <th class="num">Discount</th>
            <th class="num">VAT</th>
            <th class="num">Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($quotation->lines as $line)
            <tr>
                <td>{{ $line->line_number ?? $loop->iteration }}</td>
                <td>
                    <div><strong>{{ $line->product?->name }}</strong></div>
                    @if ($line->product?->code)<div class="meta">{{ $line->product->code }}</div>@endif
                </td>
                <td>{{ $line->uom?->code ?? '—' }}</td>
                <td class="num">{{ $qty($line->qty) }}</td>
                <td class="num">{{ $money($line->unit_price) }}</td>
                <td class="num">{{ $money($line->discount_amount) }}</td>
                <td class="num">{{ $money($line->tax_amount) }}</td>
                <td class="num"><strong>{{ $money($line->line_total) }}</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $money($quotation->subtotal) }}</td></tr>
        <tr><td>Discount</td><td class="num">-{{ $money($quotation->discount_total) }}</td></tr>
        <tr><td>VAT / Tax</td><td class="num">{{ $money($quotation->tax_total) }}</td></tr>
        <tr class="grand"><td>Grand Total (KES)</td><td class="num">{{ $money($quotation->grand_total) }}</td></tr>
    </table>

    <div class="note">
        This quotation is an estimate based on current catalogue pricing and stock availability. Prices and item availability remain subject to confirmation at the time the order is accepted.
    </div>

    @include('pdf.partials.issued-by')
@endsection
