<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 22mm 16mm 20mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1f2937; }
        .head { border-bottom: 3px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 4px; }
        .head-rule { border-bottom: 1px solid #93c5fd; margin-bottom: 14px; }
        .head h1 { font-size: 15px; margin: 0 0 4px; color: #ffffff; background: #1e3a8a; padding: 5px 10px; display: inline-block; letter-spacing: .03em; }
        .head .org { font-size: 18px; font-weight: bold; color: #1e3a8a; line-height: 1.15; }
        .head .meta { font-size: 9px; color: #6b7280; }
        .head .tagline { font-size: 9.5px; font-style: italic; color: #2563eb; margin: 1px 0 4px; }
        .head .contact { font-size: 9px; color: #374151; line-height: 1.45; }
        .head .contact b { color: #1e3a8a; }
        .brand { border-collapse: collapse; }
        .brand td { vertical-align: top; padding: 0; width: auto; }
        .brand td.logo { padding-right: 10px; }
        .brand td.logo img { max-width: 26mm; max-height: 26mm; }
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
                            <div class="contact">
                                @if ($letterhead['address'] ?? null)<div>{{ $letterhead['address'] }}</div>@endif
                                @if ($letterhead['postal_address'] ?? null)<div>{{ $letterhead['postal_address'] }}</div>@endif
                                @if (($letterhead['phone'] ?? null) || ($letterhead['email'] ?? null))
                                    <div>
                                        @if ($letterhead['phone'] ?? null)<b>Tel:</b> {{ $letterhead['phone'] }}@endif
                                        @if (($letterhead['phone'] ?? null) && ($letterhead['email'] ?? null)) &nbsp;·&nbsp; @endif
                                        @if ($letterhead['email'] ?? null)<b>Email:</b> {{ $letterhead['email'] }}@endif
                                    </div>
                                @endif
                                @if ($letterhead['website'] ?? null)<div><b>Web:</b> {{ $letterhead['website'] }}</div>@endif
                                <div>
                                    @if ($letterhead['kra_pin'])
                                        <b>KRA PIN:</b> {{ $letterhead['kra_pin'] }}
                                    @else
                                        <span class="warn">KRA PIN not set</span>
                                    @endif
                                    @if ($letterhead['branch'] ?? null) &nbsp;·&nbsp; <b>Branch:</b> {{ $letterhead['branch'] }}@endif
                                </div>
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
<div class="head-rule"></div>

@yield('body')
</body>
</html>
