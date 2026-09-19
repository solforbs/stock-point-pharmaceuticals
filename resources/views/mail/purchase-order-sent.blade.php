Purchase order {{ $purchaseOrder->doc_number }}
From: {{ $organisationName }} ({{ $branchName }})
@if ($purchaseOrder->expected_date)
Required by: {{ $purchaseOrder->expected_date->format('j M Y') }}
@endif

Please dispatch the following:

@foreach ($lines as $line)
  {{ $loop->iteration }}. {{ $line->product?->code }} — {{ $line->product?->name }}
     {{ rtrim(rtrim(number_format((float) $line->qty_ordered, 4, '.', ''), '0'), '.') }} {{ $line->uom?->code }} @ KES {{ number_format((float) $line->unit_price, 2) }}
@endforeach

Please quote {{ $purchaseOrder->doc_number }} on your delivery note and invoice,
and include the batch number and expiry date of every item delivered. Goods are
checked against this order on arrival; short or over deliveries are recorded.

This is an automated message; replies are not monitored.
