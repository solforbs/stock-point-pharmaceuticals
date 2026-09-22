<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 22mm 16mm 20mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1f2937; }
        .head { border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 14px; }
        .head h1 { font-size: 16px; margin: 0 0 2px; color: #1e3a8a; }
        .head .org { font-size: 12px; font-weight: bold; }
        .head .meta { font-size: 9px; color: #6b7280; }
        .head .tagline { font-size: 9px; font-style: italic; color: #1e3a8a; margin-bottom: 2px; }
        .brand { border-collapse: collapse; }
        .brand td { vertical-align: top; padding: 0; width: auto; }
        .brand td.logo { padding-right: 8px; }
        .brand td.logo img { max-width: 22mm; max-height: 22mm; }
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
    <table class="cols">
        <tr>
            <td>
                <table class="brand">
                    <tr>
                        @if ($letterhead['logo'] ?? null)
                            <td class="logo"><img src="{{ $letterhead['logo'] }}" alt="Logo"></td>
                        @endif
                        <td>
                            <div class="org">{{ $letterhead['legal_name'] ?: $letterhead['organisation'] }}</div>
                            @if ($letterhead['tagline'] ?? null)<div class="tagline">{{ $letterhead['tagline'] }}</div>@endif
                            <div class="meta">
                                {{ implode(' · ', array_filter([$letterhead['branch'], $letterhead['address']])) }}
                            </div>
                            @if ($letterhead['contact_line'] ?? null)<div class="meta">{{ $letterhead['contact_line'] }}</div>@endif
                            <div class="meta">
                                @if ($letterhead['kra_pin'])
                                    KRA PIN: {{ $letterhead['kra_pin'] }}
                                @else
                                    <span class="warn">KRA PIN not set</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="text-align: right;">
                <h1>{{ $title }}</h1>
                <div class="meta">{{ $docNumber }}</div>
                @isset($docDate)<div class="meta">{{ $docDate }}</div>@endisset
            </td>
        </tr>
    </table>
</div>

@yield('body')
</body>
</html>
