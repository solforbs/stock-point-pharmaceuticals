{{ $label }}

Hello {{ $order->customer?->name }},

Your order {{ $order->doc_number }} with {{ $organisationName }}:

@foreach ($timeline as $step)
{{ $step['reached'] ? '[x]' : '[ ]' }} {{ $step['label'] }}@if ($step['at']) — {{ $step['at'] }}@endif

@endforeach
@if ($note)

{{ $note }}
@endif

What you ordered:
@foreach ($order->lines as $line)
  - {{ $line->product?->name }} — {{ rtrim(rtrim(number_format((float) $line->qty, 4, '.', ''), '0'), '.') }} {{ $line->uom?->code }}
@endforeach

@if ($milestone === 'CANCELLED')
Nothing will be delivered against this order. If this is unexpected, please
reply to your usual contact at {{ $organisationName }}.
@elseif ($milestone === 'DELIVERED')
Thank you. Please check the goods against the delivery note and tell us within
48 hours if anything is short, damaged or not what you ordered.
@else
We will write again when it moves on.
@endif

This is an automated message; replies are not monitored.
