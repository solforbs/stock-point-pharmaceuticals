<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 22mm 16mm 20mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1f2937; }
        .head { border-bottom: 3px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 3px; }
        .head-rule { border-bottom: 1px solid #93c5fd; margin-bottom: 14px; }
        .masthead { width: 100%; border-collapse: collapse; }
        .masthead td { vertical-align: top; padding: 0; }
        .mh-logo { width: 32mm; padding-right: 10px !important; }
        .mh-logo img { width: 30mm; max-height: 26mm; }
        .mh-org { padding-right: 10px !important; }
        .mh-doc { width: 46mm; text-align: right; }
        .head .org { font-size: 16px; font-weight: bold; color: #1e3a8a; line-height: 1.2; }
        .head .tagline { font-size: 9px; font-style: italic; color: #15803d; margin: 1px 0 5px; }
        .head .contact { border-collapse: collapse; font-size: 8.5px; color: #374151; }
        .head .contact td { padding: 0 0 1.5px 0; vertical-align: top; }
        .head .contact td.k, .head .contact span.k { color: #1e3a8a; font-weight: bold; padding-right: 6px; white-space: nowrap; }
        .head .contact .sep { color: #9ca3af; padding: 0 5px; }
        .head .meta { font-size: 9px; color: #6b7280; }
        .doc-title { background: #1e3a8a; color: #ffffff; font-size: 12px; font-weight: bold; padding: 6px 8px; text-align: center; letter-spacing: .04em; text-transform: uppercase; }
        .doc-meta { font-size: 9px; color: #6b7280; margin-top: 3px; }
        .issued-by { width: 100%; margin-top: 22px; page-break-inside: avoid; }
        .issued-by td { vertical-align: bottom; padding: 0; }
        .issued-by img.stamp { max-width: 34mm; max-height: 34mm; }
        .issued-by img.signature { max-width: 45mm; max-height: 18mm; }
        .issued-by .line { border-top: 1px solid #9ca3af; padding-top: 3px; margin-top: 2px; width: 62mm; }
        .issued-by .meta { font-size: 9px; color: #6b7280; }
        .cols { width: 100%; }
        .cols td { vertical-align: top; width: 50%; padding: 0; }
        h2 { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin: 0 0 3px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th { background: #f3f4f6; text-align: left; padding: 5px 6px; font-size: 9px; text-transform: uppercase; border-bottom: 1px solid #d1d5db; }
        table.items td { padding: 5px 6px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; }
        .totals { width: 42%; margin-left: 58%; margin-top: 10px; border-collapse: collapse; }
        .totals td { padding: 3px 6px; }
        .totals tr.grand td { border-top: 1.5px solid #1e3a8a; font-weight: bold; font-size: 11px; }
        .note { margin-top: 14px; font-size: 9px; color: #6b7280; }
        .warn { color: #b45309; font-weight: bold; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<footer>{{ $letterhead['organisation'] }} · {{ $title }} · printed {{ $letterhead['printed_at'] }}</footer>

<div class="head">
    <table class="masthead">
        <tr>
            @if ($letterhead['logo'] ?? null)
                <td class="mh-logo"><img src="{{ $letterhead['logo'] }}" alt="Logo"></td>
            @endif
            <td class="mh-org">
                <div class="org">{{ $letterhead['legal_name'] ?: $letterhead['organisation'] }}</div>
                @if ($letterhead['tagline'] ?? null)<div class="tagline">{{ $letterhead['tagline'] }}</div>@endif
                <table class="contact">
                    @if (($letterhead['address'] ?? null) || ($letterhead['postal_address'] ?? null))
                        <tr><td class="k">Address</td><td>{{ implode(', ', array_filter([$letterhead['address'] ?? null, $letterhead['postal_address'] ?? null])) }}</td></tr>
                    @endif
                    @if ($letterhead['phone'] ?? null)<tr><td class="k">Tel</td><td>{{ $letterhead['phone'] }}</td></tr>@endif
                    @if ($letterhead['email'] ?? null)<tr><td class="k">Email</td><td>{{ $letterhead['email'] }}</td></tr>@endif
                    @if ($letterhead['website'] ?? null)<tr><td class="k">Web</td><td>{{ $letterhead['website'] }}</td></tr>@endif
                    {{-- A document can leave the KRA PIN off (the price list); tax documents always carry it. --}}
                    @if ($hideKraPin ?? false)
                        @if ($letterhead['branch'] ?? null)<tr><td class="k">Branch</td><td>{{ $letterhead['branch'] }}</td></tr>@endif
                    @else
                        <tr>
                            <td class="k">KRA PIN</td>
                            <td>
                                @if ($letterhead['kra_pin']){{ $letterhead['kra_pin'] }}@else<span class="warn">not set</span>@endif
                                @if ($letterhead['branch'] ?? null)<span class="sep">|</span><span class="k">Branch</span> {{ $letterhead['branch'] }}@endif
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
            <td class="mh-doc">
                <div class="doc-title">{{ $title }}</div>
                <div class="doc-meta">{{ $docNumber }}</div>
                @isset($docDate)<div class="doc-meta">{{ $docDate }}</div>@endisset
            </td>
        </tr>
    </table>
</div>
<div class="head-rule"></div>

@yield('body')
</body>
</html>
