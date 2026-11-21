@extends('pdf.layout', ['title' => $title, 'docNumber' => $requisition->doc_number, 'docDate' => $requisition->created_at?->format('j M Y, H:i')])

@section('body')
    <table class="cols">
        <tr>
            <td>
                <h2>Requested for</h2>
                <div><strong>{{ $requisition->branch?->name }}</strong></div>
                @if ($requisition->branch?->address)<div>{{ $requisition->branch->address }}</div>@endif
                @if ($requisition->needed_by)<div>Needed by: <strong>{{ $requisition->needed_by->format('j M Y') }}</strong></div>@endif
            </td>
            <td>
                <h2>Request</h2>
                <div>Status: <strong>{{ str_replace('_', ' ', $requisition->status) }}</strong></div>
                @if ($requisition->requester?->name)<div>Requested by: {{ $requisition->requester->name }}</div>@endif
                @if ($requisition->approver?->name)<div>Approved by: {{ $requisition->approver->name }}</div>@endif
                @if ($requisition->notes)<div class="meta">{{ $requisition->notes }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th>#</th>
            <th>Item description</th>
            <th>Unit</th>
            <th class="num">Qty requested</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($requisition->lines as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    <div><strong>{{ $line->product?->name }}</strong></div>
                    @if ($line->product?->code)<div class="meta">{{ $line->product->code }}</div>@endif
                    @if ($line->notes)<div class="meta">{{ $line->notes }}</div>@endif
                </td>
                <td>{{ $line->product?->baseUom?->code ?? '—' }}</td>
                <td class="num">{{ $qty($line->qty_requested) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="note">
        This is a purchase request, not an order. Prices are quoted separately by each supplier.
    </div>

    <table class="cols" style="margin-top: 30px;">
        <tr>
            <td>
                <div>Requested by: .............................................</div>
                <div style="margin-top: 14px;">Signature: .............................................</div>
            </td>
            <td>
                <div>Approved by: .............................................</div>
                <div style="margin-top: 14px;">Date: .............................................</div>
            </td>
        </tr>
    </table>
@endsection
