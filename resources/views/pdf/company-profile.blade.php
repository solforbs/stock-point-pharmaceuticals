@php
    $name = $profile->legal_name ?: $profile->name;
    $coreValues = $profile->core_values ?? [];
    $services = $profile->services ?? [];
    $todo = 'To be completed.';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $name }}</title>
    <style>
        @page { margin: 22mm 18mm 20mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5px; color: #1f2937; line-height: 1.5; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
        .cover { text-align: center; padding-top: 55mm; page-break-after: always; }
        .cover img { max-width: 60mm; max-height: 45mm; margin-bottom: 10mm; }
        .cover h1 { font-size: 26px; color: #1e3a8a; margin: 0 0 6px; }
        .cover .tagline { font-size: 13px; font-style: italic; color: #374151; margin-bottom: 18mm; }
        .cover .kind { font-size: 12px; letter-spacing: .2em; text-transform: uppercase; color: #6b7280; border-top: 1px solid #d1d5db; border-bottom: 1px solid #d1d5db; padding: 6px 0; margin: 0 30mm; }
        .cover .contact { margin-top: 30mm; font-size: 9.5px; color: #6b7280; }
        h2 { font-size: 14px; color: #1e3a8a; border-bottom: 1.5px solid #1e3a8a; padding-bottom: 3px; margin: 18px 0 8px; page-break-after: avoid; }
        .todo { color: #9ca3af; font-style: italic; border: 1px dashed #d1d5db; padding: 8px 10px; }
        .statement { font-size: 12px; font-style: italic; color: #111827; border-left: 3px solid #1e3a8a; padding: 4px 0 4px 10px; }
        ul { margin: 0; padding-left: 16px; }
        li { margin-bottom: 3px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #f3f4f6; text-align: left; padding: 5px 6px; font-size: 9px; text-transform: uppercase; border-bottom: 1px solid #d1d5db; }
        table.grid td { padding: 5px 6px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        table.contacts td { padding: 3px 0; vertical-align: top; }
        table.contacts td.label { width: 38mm; color: #6b7280; }
        .meta { font-size: 9px; color: #6b7280; }
        .issued-by { width: 100%; margin-top: 26px; page-break-inside: avoid; }
        .issued-by td { vertical-align: bottom; padding: 0; }
        .issued-by img.stamp { max-width: 34mm; max-height: 34mm; }
        .issued-by img.signature { max-width: 45mm; max-height: 18mm; }
        .issued-by .line { border-top: 1px solid #9ca3af; padding-top: 3px; margin-top: 2px; width: 62mm; }
    </style>
</head>
<body>
<footer>{{ $name }} · Company profile · printed {{ $letterhead['printed_at'] }}</footer>

<div class="cover">
    @if ($letterhead['logo'])
        <div><img src="{{ $letterhead['logo'] }}" alt="Logo"></div>
    @endif
    <h1>{{ $name }}</h1>
    @if ($profile->tagline)
        <div class="tagline">{{ $profile->tagline }}</div>
    @else
        <div class="tagline" style="color: #9ca3af;">Tagline to be completed</div>
    @endif
    <div class="kind">Company profile</div>
    @if ($letterhead['contact_line'])
        <div class="contact">{{ $letterhead['contact_line'] }}</div>
    @endif
</div>

<h2>About us</h2>
@if ($profile->about)
    <div>{!! nl2br(e($profile->about)) !!}</div>
@else
    <div class="todo">{{ $todo }} Who we are, when we started and what we do.</div>
@endif

<h2>Mission</h2>
@if ($profile->mission)
    <div class="statement">{!! nl2br(e($profile->mission)) !!}</div>
@else
    <div class="todo">{{ $todo }}</div>
@endif

<h2>Vision</h2>
@if ($profile->vision)
    <div class="statement">{!! nl2br(e($profile->vision)) !!}</div>
@else
    <div class="todo">{{ $todo }}</div>
@endif

<h2>Core values</h2>
@if (count($coreValues) > 0)
    <ul>
        @foreach ($coreValues as $value)
            <li>{{ $value }}</li>
        @endforeach
    </ul>
@else
    <div class="todo">{{ $todo }}</div>
@endif

<h2>Services</h2>
@if (count($services) > 0)
    <ul>
        @foreach ($services as $service)
            <li>{{ $service }}</li>
        @endforeach
    </ul>
@else
    <div class="todo">{{ $todo }}</div>
@endif

<h2>Licences and certificates</h2>
@if (count($licences) > 0)
    <table class="grid">
        <thead><tr><th>Licence</th><th>Number</th><th>Issued by</th><th>Valid until</th></tr></thead>
        <tbody>
        @foreach ($licences as $licence)
            <tr>
                <td>{{ $licence['type'] }}@if ($licence['holder'])<div class="meta">{{ $licence['holder'] }}</div>@endif</td>
                <td>{{ $licence['number'] }}</td>
                <td>{{ $licence['issued_by'] ?? '—' }}</td>
                <td>{{ $licence['expiry'] }}@if ($licence['status'] === 'EXPIRED')<div class="meta">Expired — renewal in progress</div>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <div class="todo">{{ $todo }} Add licences under Quality › Licences &amp; Certificates.</div>
@endif

<h2>Branches</h2>
@if (count($branches) > 0)
    <table class="grid">
        <thead><tr><th>Branch</th><th>Address</th><th>County</th></tr></thead>
        <tbody>
        @foreach ($branches as $branch)
            <tr>
                <td>{{ $branch->name }}</td>
                <td>{{ $branch->address ?: '—' }}</td>
                <td>{{ $branch->county ?: '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <div class="todo">{{ $todo }}</div>
@endif

<h2>Contacts</h2>
<table class="contacts">
    @foreach ([
        'Physical address' => $profile->physical_address,
        'Postal address' => $profile->postal_address,
        'Phone' => $profile->contact_phone,
        'Email' => $profile->contact_email,
        'Website' => $profile->website,
        'KRA PIN' => $letterhead['kra_pin'],
    ] as $label => $value)
        <tr>
            <td class="label">{{ $label }}</td>
            <td>@if ($value){{ $value }}@else<span class="meta"><em>{{ $todo }}</em></span>@endif</td>
        </tr>
    @endforeach
</table>

@include('pdf.partials.issued-by')
</body>
</html>
