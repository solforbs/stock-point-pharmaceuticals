@extends('pdf.layout', ['title' => $title, 'docNumber' => $rfq->doc_number, 'docDate' => ($rfq->sent_at ?? $rfq->created_at)?->format('j M Y')])

@section('body')
    <table class="cols">
        <tr>
            <td>
                <h2>To supplier</h2>
                <div><strong>{{ $supplier?->name }}</strong></div>
                @if ($supplier?->code)<div class="meta">Supplier code: {{ $supplier->code }}</div>@endif
                @if ($supplier?->contact_name)<div>Attn: {{ $supplier->contact_name }}</div>@endif
                @if ($supplier?->address)<div>{{ $supplier->address }}</div>@endif
                @if ($supplier?->phone)<div>Tel: {{ $supplier->phone }}</div>@endif
                @if ($supplier?->email)<div>Email: {{ $supplier->email }}</div>@endif
            </td>
            <td>
                <h2>Request</h2>
                <div><strong>{{ $rfq->title }}</strong></div>
                <div>Deliver to: <strong>{{ $rfq->branch?->name }}</strong></div>
                @if ($rfq->branch?->address)<div>{{ $rfq->branch->address }}</div>@endif
                @if ($rfq->needed_by)<div>Goods needed by: <strong>{{ $rfq->needed_by->format('j M Y') }}</strong></div>@endif
                @if ($rfq->creator?->name)<div class="meta">Contact: {{ $rfq->creator->name }}</div>@endif
            </td>
        </tr>
    </table>

    <p style="margin-top: 12px;">Please quote your best price for the items below. Fill in each line you can supply and leave blank any you cannot.</p>

    <table class="items">
        <thead>
        <tr>
            <th>#</th>
            <th>Item</th>
            <th>Unit</th>
            <th class="num">Qty needed</th>
            <th class="num">Unit price (KES)</th>
            <th class="num">Qty you can supply</th>
            <th class="num">Lead time (days)</th>
            <th class="num">Shelf life (months)</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rfq->lines as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    <div><strong>{{ $line->product?->name }}</strong>@if ($line->product?->strength) {{ $line->product->strength }}@endif</div>
                    @if ($line->product?->code)<div class="meta">{{ $line->product->code }}</div>@endif
                    @if ($line->notes)<div class="meta">{{ $line->notes }}</div>@endif
                </td>
                <td>{{ $line->uom?->code ?? '—' }}</td>
                <td class="num">{{ $qty($line->qty) }}</td>
                <td class="num">..............</td>
                <td class="num">..........</td>
                <td class="num">........</td>
                <td class="num">........</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="cols" style="margin-top: 18px;">
        <tr>
            <td>
                <div>Payment terms (days): .............................</div>
                <div style="margin-top: 10px;">Quote valid until: .............................</div>
                <div style="margin-top: 10px;">Delivery charges (KES): .............................</div>
            </td>
            <td>
                <div>Your quote reference: .............................</div>
                <div style="margin-top: 10px;">Signed: .............................</div>
                <div style="margin-top: 10px;">Date: .............................</div>
            </td>
        </tr>
    </table>

    @if ($rfq->notes)
        <div class="note"><strong>Notes:</strong> {{ $rfq->notes }}</div>
    @endif

    <div class="note">
        Prices in Kenya shillings, including VAT where it applies. Goods must meet Pharmacy and Poisons Board (PPB) standards and come with the delivery note, tax invoice and certificate of analysis where applicable. Please attach a copy of your current PPB licence. Quotes are compared on price, delivery time, payment terms and supplier record.
    </div>
@endsection
