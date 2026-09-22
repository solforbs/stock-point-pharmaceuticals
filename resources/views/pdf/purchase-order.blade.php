@extends('pdf.layout', ['title' => $title, 'docNumber' => $order->doc_number, 'docDate' => $order->created_at?->format('j M Y, H:i')])

@section('body')
    <table class="cols">
        <tr>
            <td>
                <h2>Vendor / Supplier</h2>
                <div><strong>{{ $order->supplier?->name }}</strong></div>
                @if ($order->supplier?->code)<div class="meta">Vendor code: {{ $order->supplier->code }}</div>@endif
                @if ($order->supplier?->address)<div>{{ $order->supplier->address }}</div>@endif
                @if ($order->supplier?->phone)<div>Tel: {{ $order->supplier->phone }}</div>@endif
                @if ($order->supplier?->email)<div>Email: {{ $order->supplier->email }}</div>@endif
                @if ($order->supplier?->kra_pin)<div class="meta">KRA PIN: {{ $order->supplier->kra_pin }}</div>@endif
            </td>
            <td>
                <h2>Delivery & Fulfillment</h2>
                <div>Deliver to: <strong>{{ $order->branch?->name }}</strong></div>
                @if ($order->branch?->address)<div>{{ $order->branch->address }}</div>@endif
                @if ($order->expected_date)<div>Expected by: <strong>{{ $order->expected_date->format('j M Y') }}</strong></div>@endif
                <div>PO Status: <strong>{{ $order->status }}</strong></div>
                @if ($order->sent_at)<div class="meta">Sent to vendor: {{ $order->sent_at->format('j M Y, H:i') }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th>#</th>
            <th>Item description</th>
            <th>UOM</th>
            <th class="num">Qty ordered</th>
            <th class="num">Unit price</th>
            <th class="num">Total amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($order->lines as $line)
            @php
                $lineTotal = bcmul((string) $line->qty_ordered, (string) $line->unit_price, 4);
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    <div><strong>{{ $line->product?->name }}</strong></div>
                    @if ($line->product?->code)<div class="meta">{{ $line->product->code }}</div>@endif
                </td>
                <td>{{ $line->uom?->code ?? '—' }}</td>
                <td class="num">{{ $qty($line->qty_ordered) }}</td>
                <td class="num">{{ $money($line->unit_price) }}</td>
                <td class="num"><strong>{{ $money($lineTotal) }}</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand"><td>Total Order Value (KES)</td><td class="num">{{ $money($total) }}</td></tr>
    </table>

    <div class="note">
        All pharmaceutical goods delivered must comply with Kenya Pharmacy and Poisons Board (PPB) standards and have at least 75% shelf life remaining unless pre-authorized. Include original delivery note, tax invoice, and certificates of analysis (CoA) where applicable.
    </div>

    <table class="cols" style="margin-top: 30px;">
        <tr>
            <td>
                <div>Authorized by: .............................................</div>
                <div style="margin-top: 14px;">Signature: .............................................</div>
            </td>
            <td>
                <div>Vendor acceptance: .............................................</div>
                <div style="margin-top: 14px;">Date: .............................................</div>
            </td>
        </tr>
    </table>

    @include('pdf.partials.issued-by')
@endsection
