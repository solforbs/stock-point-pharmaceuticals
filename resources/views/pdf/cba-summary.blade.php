@extends('pdf.layout', ['title' => $title, 'docNumber' => $rfq->doc_number, 'docDate' => ($rfq->awarded_at ?? now())->format('j M Y, H:i')])

@php
    $suppliers = $analysis['suppliers'];
    $overall = $analysis['overall'];
    $weights = $analysis['weights'];
    $plan = (array) $overall['plan'];
@endphp

@section('body')
    <style>
        table.matrix td, table.matrix th { font-size: 8.5px; padding: 4px 5px; }
        .best { background: #ecfdf5; }
        .rec { font-weight: bold; color: #065f46; }
        .risk { color: #b45309; }
        .small { font-size: 8.5px; color: #6b7280; }
        h3 { font-size: 11px; color: #1e3a8a; margin: 16px 0 4px; }
    </style>

    <table class="cols">
        <tr>
            <td>
                <h2>Request</h2>
                <div><strong>{{ $rfq->title }}</strong></div>
                @if ($rfq->needed_by)<div>Needed by: {{ $rfq->needed_by->format('j M Y') }}</div>@endif
                <div>Status: <strong>{{ $rfq->status }}</strong></div>
                @if ($rfq->creator?->name)<div class="meta">Prepared by {{ $rfq->creator->name }}</div>@endif
            </td>
            <td>
                <h2>How quotes are scored</h2>
                <div>Price {{ rtrim(rtrim(number_format($weights['price'], 2), '0'), '.') }}% · Lead time {{ rtrim(rtrim(number_format($weights['lead_time'], 2), '0'), '.') }}% · Payment terms {{ rtrim(rtrim(number_format($weights['payment_terms'], 2), '0'), '.') }}% · Supplier record {{ rtrim(rtrim(number_format($weights['supplier_record'], 2), '0'), '.') }}%</div>
                <div class="small">Each criterion is scored out of 100: the cheapest price, the fastest delivery and the longest credit score 100, others in proportion. The supplier record comes from past invoices, deliveries and licence status.</div>
            </td>
        </tr>
    </table>

    <h3>Suppliers asked</h3>
    <table class="items">
        <thead>
        <tr>
            <th>Supplier</th>
            <th>Quote</th>
            <th class="num">Terms (days)</th>
            <th class="num">Delivery (KES)</th>
            <th class="num">Record</th>
            <th class="num">Total incl. delivery</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($suppliers as $supplier)
            <tr>
                <td>
                    <strong>{{ $supplier['name'] }}</strong>
                    <div class="small">{{ $supplier['record']['summary'] }}</div>
                    @foreach ($supplier['risks'] as $risk)
                        <div class="small risk">! {{ $risk['message'] }}</div>
                    @endforeach
                </td>
                <td>
                    @if ($supplier['quote_status'] === 'RECEIVED')
                        {{ $supplier['quote_reference'] ?: 'Received' }}
                        @if ($supplier['valid_until'])<div class="small">Valid to {{ \Illuminate\Support\Carbon::parse($supplier['valid_until'])->format('j M Y') }}</div>@endif
                    @elseif ($supplier['quote_status'] === 'DECLINED')
                        Declined
                    @else
                        No quote
                    @endif
                </td>
                <td class="num">{{ $supplier['payment_terms_days'] ?? '—' }}</td>
                <td class="num">{{ $supplier['quote_status'] === 'RECEIVED' ? $money($supplier['delivery_charge']) : '—' }}</td>
                <td class="num">{{ number_format($supplier['record']['score'], 1) }}</td>
                <td class="num">{{ ($supplier['lines_quoted'] ?? 0) > 0 ? $money($supplier['total_with_delivery']) : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h3>Bid matrix (unit price in KES · score out of 100)</h3>
    <table class="items matrix">
        <thead>
        <tr>
            <th>Item</th>
            <th class="num">Qty</th>
            @foreach ($suppliers as $supplier)
                <th class="num">{{ $supplier['name'] }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($analysis['lines'] as $line)
            @php $quotes = collect($line['quotes'])->keyBy('supplier_id'); @endphp
            <tr>
                <td><strong>{{ $line['product']['name'] }}</strong> <span class="small">{{ $line['uom']['code'] }}</span></td>
                <td class="num">{{ $qty($line['qty']) }}</td>
                @foreach ($suppliers as $supplier)
                    @php $quote = $quotes[$supplier['supplier_id']] ?? null; @endphp
                    <td class="num {{ $quote && $quote['is_lowest'] ? 'best' : '' }}">
                        @if ($quote)
                            <span class="{{ ($plan[$line['rfq_line_id']] ?? null) === $supplier['supplier_id'] ? 'rec' : '' }}">{{ $money($quote['unit_price']) }}</span>
                            <div class="small">score {{ number_format($quote['scores']['total'], 1) }} · {{ $quote['lead_time_days'] }} d</div>
                            @if (! $quote['full_quantity'])<div class="small risk">only {{ $qty($quote['qty_available']) }}</div>@endif
                        @else
                            <span class="small">not quoted</span>
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="small">Shaded: lowest price on the line. Bold green: the recommended supplier for the line.</div>

    <h3>Recommendation by line</h3>
    <table class="items">
        <thead><tr><th>Item</th><th>Best on this line</th><th>Why</th></tr></thead>
        <tbody>
        @foreach ($analysis['lines'] as $line)
            <tr>
                <td>{{ $line['product']['name'] }}</td>
                <td>{{ $line['recommendation']['supplier_name'] ?? '—' }}</td>
                <td>{{ $line['recommendation']['summary'] ?? $line['no_recommendation_reason'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h3>Overall recommendation</h3>
    <div>
        <strong>
            @if ($overall['mode'] === 'SINGLE')
                Single supplier: {{ $overall['supplier_name'] }}
            @elseif ($overall['mode'] === 'SPLIT')
                Split award
            @else
                None yet
            @endif
        </strong>
        @if ($overall['mode'] !== 'NONE') · KES {{ $money($overall['total']) }} including delivery @endif
    </div>
    <div>{{ $overall['summary'] }}</div>
    @if ($overall['mode'] === 'SPLIT')
        <div class="small">
            @foreach ($analysis['lines'] as $line)
                @if (isset($plan[$line['rfq_line_id']])){{ $line['product']['name'] }} → {{ $supplierNames[$plan[$line['rfq_line_id']]] ?? '' }}@if (! $loop->last); @endif @endif
            @endforeach
        </div>
    @endif
    @foreach ($overall['risks'] as $risk)
        <div class="risk">! {{ $risk['message'] }}</div>
    @endforeach

    <h3>Decision</h3>
    @if ($rfq->status === 'AWARDED')
        <table class="items">
            <thead><tr><th>Item</th><th>Awarded to</th><th>Recommended</th></tr></thead>
            <tbody>
            @foreach ($analysis['lines'] as $line)
                @php
                    $awarded = $awardedBySupplier[$line['rfq_line_id']] ?? null;
                    $recommended = $plan[$line['rfq_line_id']] ?? null;
                @endphp
                <tr>
                    <td>{{ $line['product']['name'] }}</td>
                    <td>{{ $awarded ? ($supplierNames[$awarded] ?? '') : 'Not awarded' }}</td>
                    <td class="{{ $awarded !== $recommended ? 'risk' : '' }}">{{ $recommended ? ($supplierNames[$recommended] ?? '') : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top: 8px;">
            {{ $rfq->award_followed_recommendation ? 'The recommendation was accepted.' : 'The award departs from the recommendation.' }}
            Awarded as a {{ strtolower((string) $rfq->award_mode) }} award by <strong>{{ $rfq->awarder?->name ?? '—' }}</strong> on {{ $rfq->awarded_at?->format('j M Y, H:i') }}.
        </div>
        @if ($rfq->award_justification)
            <div style="margin-top: 6px;"><strong>Justification:</strong> {{ $rfq->award_justification }}</div>
        @endif
        @if ($rfq->purchaseOrders->isNotEmpty())
            <div style="margin-top: 6px;">Purchase orders raised (draft, awaiting approval):
                @foreach ($rfq->purchaseOrders as $po){{ $po->doc_number }} to {{ $po->supplier?->name }}@if (! $loop->last), @endif @endforeach
            </div>
        @endif
    @else
        <div>Not yet awarded.</div>
    @endif

    <table class="cols" style="margin-top: 26px;">
        <tr>
            <td><div>Reviewed by: .............................................</div></td>
            <td><div>Signature and date: .............................................</div></td>
        </tr>
    </table>
@endsection
