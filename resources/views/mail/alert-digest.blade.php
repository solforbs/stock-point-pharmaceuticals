{{ $branchName }} — alerts as at {{ now()->format('D, j M Y') }}

Hello {{ $recipientName }},

@foreach ($groups as $category => $items)
{{ $headings[$category] ?? $category }} ({{ $items->count() }})
@foreach ($items as $alert)
  - [{{ $alert->severity }}] {{ $alert->title }}
    {{ $alert->detail }}
@if ($alert->due_date)
    Due {{ $alert->due_date->format('j M Y') }}@if ($alert->days_to_due !== null) ({{ $alert->days_to_due < 0 ? abs($alert->days_to_due).' days late' : 'in '.$alert->days_to_due.' days' }})@endif

@endif
@endforeach

@endforeach
Open the alert centre: {{ rtrim($appUrl, '/') }}/spa/admin/alerts

An alert disappears from this digest by itself once the invoice is paid or
the stock is gone; marking one as seen only silences it.

This is an automated message; replies are not monitored.
