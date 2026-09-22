@extends('pdf.layout', ['title' => $title, 'docNumber' => $note->doc_number, 'docDate' => $note->dispatched_at?->format('j M Y, H:i')])

@section('body')
    <table class="cols">
        <tr>
            <td>
                <h2>Deliver to</h2>
                <div><strong>{{ $note->salesOrder?->customer?->name }}</strong></div>
                @if ($note->salesOrder?->customer?->address)<div>{{ $note->salesOrder->customer->address }}</div>@endif
                @if ($note->salesOrder?->customer?->phone)<div>{{ $note->salesOrder->customer->phone }}</div>@endif
                @if ($note->salesOrder?->doc_number)<div class="meta">Against order {{ $note->salesOrder->doc_number }}</div>@endif
            </td>
            <td>
                <h2>Transport</h2>
                <div>Mode: {{ \App\Models\DeliveryNote::DELIVERY_MODE_LABELS[$note->delivery_mode] ?? '—' }}</div>
                @if ($note->vehicle_reg)<div>Registration: {{ $note->vehicle_reg }}</div>@endif
                <div>{{ $note->delivery_mode === 'CUSTOMER_PICKUP' ? 'Collected by' : ($note->delivery_mode === 'HAND' ? 'Delivered by' : 'Rider / driver') }}: {{ $note->driver_name ?: '—' }}</div>
                @if ($note->driver_phone)<div>{{ $note->driver_phone }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr><th>#</th><th>Item</th><th>Batch</th><th>Expiry</th><th class="num">Quantity</th></tr>
        </thead>
        <tbody>
        @foreach ($note->lines as $line)
            @forelse ($line->batchAllocations as $allocation)
                <tr>
                    <td>{{ $loop->parent->iteration }}</td>
                    <td>{{ $line->product?->name }}</td>
                    <td>{{ $allocation->batch?->batch_number }}</td>
                    <td>{{ $allocation->batch?->expiry_date?->format('M Y') }}</td>
                    <td class="num">{{ $qty($allocation->qty_base) }}</td>
                </tr>
            @empty
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $line->product?->name }}</td>
                    <td colspan="2">—</td>
                    <td class="num">{{ $qty($line->qty_base) }}</td>
                </tr>
            @endforelse
        @endforeach
        </tbody>
    </table>

    <div class="note">
        Please check the goods before signing. Tell us within 48 hours if anything is short, damaged or not what you ordered.
    </div>

    <table class="cols" style="margin-top: 26px;">
        <tr>
            <td>
                <div>Received by: {{ $note->received_by_name ?: '.............................................' }}</div>
                <div style="margin-top: 14px;">Signature: .............................................</div>
            </td>
            <td>
                <div>Date: {{ $note->delivered_at?->format('j M Y') ?: '.............................................' }}</div>
                <div style="margin-top: 14px;">Stamp:</div>
            </td>
        </tr>
    </table>

    @include('pdf.partials.issued-by')
@endsection
