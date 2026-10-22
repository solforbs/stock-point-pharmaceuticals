<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 22mm 16mm 20mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1f2937; }
        .head { border-bottom: 3px solid #1e3a8a; padding-bottom: 4px; margin-bottom: 3px; }
        .head-rule { border-bottom: 1px solid #93c5fd; margin-bottom: 14px; }
        .masthead { width: 100%; border-collapse: collapse; }
        .masthead td { vertical-align: top; padding: 0; }
        .mh-left { width: 50%; }
        .mh-left img { width: 34mm; max-height: 28mm; }
        .mh-right { width: 50%; text-align: right; }
        .head .org { font-size: 16px; font-weight: bold; color: #1e3a8a; line-height: 1.2; }
        .head .tagline { font-size: 8.5px; font-style: italic; color: #15803d; margin-top: 2px; }
        .head .contact { border-collapse: collapse; font-size: 8.5px; color: #374151; }
        .head .contact td { padding: 0 0 2px 0; vertical-align: top; text-align: left; }
        .head .contact td.k { color: #1e3a8a; font-weight: bold; padding-right: 8px; white-space: nowrap; }
        .head .meta { font-size: 9px; color: #6b7280; }
        .doc-band { margin-top: 10px; background: #1e3a8a; color: #ffffff; font-size: 13px; font-weight: bold; padding: 6px 10px; text-align: center; white-space: nowrap; letter-spacing: .02em; }
        .doc-line { width: 100%; border-collapse: collapse; margin-top: 3px; }
        .doc-line td { font-size: 9px; color: #6b7280; padding: 0; }
        .doc-line td.r { text-align: right; }
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
    {{-- Logo on the left, contact details on the right, space between. --}}
    <table class="masthead">
        <tr>
            <td class="mh-left">
                @if ($letterhead['logo'] ?? null)
                    <img src="{{ $letterhead['logo'] }}" alt="Logo">
                @else
                    <div class="org">{{ $letterhead['legal_name'] ?: $letterhead['organisation'] }}</div>
                @endif
                @if ($letterhead['tagline'] ?? null)<div class="tagline">{{ $letterhead['tagline'] }}</div>@endif
            </td>
            <td class="mh-right">
                <table class="contact" align="right">
                    @if (($letterhead['address'] ?? null) || ($letterhead['postal_address'] ?? null))
                        <tr><td class="k">Address</td><td>{{ implode(', ', array_filter([$letterhead['address'] ?? null, $letterhead['postal_address'] ?? null])) }}</td></tr>
                    @endif
                    @if ($letterhead['phone'] ?? null)<tr><td class="k">Tel</td><td>{{ $letterhead['phone'] }}</td></tr>@endif
                    @if ($letterhead['email'] ?? null)<tr><td class="k">Email</td><td>{{ $letterhead['email'] }}</td></tr>@endif
                    @if ($letterhead['website'] ?? null)<tr><td class="k">Web</td><td>{{ $letterhead['website'] }}</td></tr>@endif
                    {{-- A document can leave the KRA PIN off (the price list); tax documents always carry it. --}}
                    @unless ($hideKraPin ?? false)
                        <tr><td class="k">KRA PIN</td><td>@if ($letterhead['kra_pin']){{ $letterhead['kra_pin'] }}@else<span class="warn">not set</span>@endif</td></tr>
                    @endunless
                    @if ($letterhead['branch'] ?? null)<tr><td class="k">Branch</td><td>{{ $letterhead['branch'] }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    {{-- One line: who issued it and what it is. --}}
    <div class="doc-band">{{ $letterhead['legal_name'] ?: $letterhead['organisation'] }} - {{ $title }}</div>
    <table class="doc-line">
        <tr>
            <td>{{ $docNumber }}</td>
            <td class="r">@isset($docDate){{ $docDate }}@endisset</td>
        </tr>
    </table>
</div>
<div class="head-rule"></div>

@yield('body')
</body>
</html>
