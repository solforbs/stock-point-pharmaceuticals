@extends('pdf.layout', ['title' => $title, 'docNumber' => $grn->doc_number, 'docDate' => ($grn->received_at ?? $grn->created_at)?->format('j M Y, H:i')])

@section('body')
    <table class="cols">
        <tr>
            <td>
                <h2>Received from</h2>
                <div><strong>{{ $grn->supplier?->name }}</strong></div>
                @if ($grn->supplier?->code)<div class="meta">Supplier code: {{ $grn->supplier->code }}</div>@endif
                @if ($grn->supplier?->kra_pin)<div class="meta">KRA PIN: {{ $grn->supplier->kra_pin }}</div>@endif
                @if ($grn->purchaseOrder?->doc_number)
                    <div class="meta">Against order {{ $grn->purchaseOrder->doc_number }}</div>
                @elseif ($grn->is_emergency)
                    <div class="warn">Emergency receipt, no purchase order</div>
                @endif
            </td>
            <td>
                <h2>Received into</h2>
                <div>Store: <strong>{{ $grn->store?->name }}</strong> @if ($grn->store?->code)({{ $grn->store->code }})@endif</div>
                <div>Received by: {{ $grn->receiver?->name ?? '—' }}</div>
                <div>Status: <strong>{{ $grn->status }}</strong></div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th>#</th>
            <th>Item</th>
            <th>Batch</th>
            <th>Expiry</th>
            <th>UOM</th>
            <th class="num">Delivered</th>
            <th class="num">Accepted</th>
            <th class="num">Rejected</th>
            <th class="num">Unit cost</th>
            <th class="num">Value</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($grn->lines as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    <div><strong>{{ $line->product?->name }}</strong></div>
                    @if ($line->product?->code)<div class="meta">{{ $line->product->code }}</div>@endif
                    @if (bccomp((string) $line->qty_rejected, '0', 4) > 0 && $line->rejection_reason)
                        <div class="warn">Rejected: {{ $line->rejection_reason }}</div>
                    @endif
                </td>
                <td>{{ $line->batch_number ?: '—' }}</td>
                <td>{{ $line->expiry_date?->format('M Y') ?? '—' }}</td>
                <td>{{ $line->uom?->code ?? '—' }}</td>
                <td class="num">{{ $qty($line->qty_delivered) }}</td>
                <td class="num">{{ $qty($line->qty_accepted) }}</td>
                <td class="num">{{ $qty($line->qty_rejected) }}</td>
                <td class="num">
                    {{ $money($line->unit_cost) }}
                    @if ($line->trade_price !== null)<div class="meta">Trade {{ $money($line->trade_price) }} less {{ rtrim(rtrim((string) $line->discount_pct, '0'), '.') ?: '0' }}%</div>@endif
                </td>
                <td class="num">{{ $money(bcmul((string) $line->qty_accepted, (string) $line->unit_cost, 4)) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand"><td>Value accepted (KES, excl. VAT)</td><td class="num">{{ $money($total) }}</td></tr>
    </table>

    <div class="note">
        Only accepted quantities enter stock. Rejected goods are returned to the supplier with this note.
    </div>

    <table class="cols" style="margin-top: 26px;">
        <tr>
            <td>
                <div>Checked by: .............................................</div>
                <div style="margin-top: 14px;">Signature: .............................................</div>
            </td>
            <td>
                <div>Supplier's driver: .............................................</div>
                <div style="margin-top: 14px;">Signature: .............................................</div>
            </td>
        </tr>
    </table>
@endsection
