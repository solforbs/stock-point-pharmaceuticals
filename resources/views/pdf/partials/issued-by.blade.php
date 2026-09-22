{{-- The official stamp and authorised signature on an issued document, printed only once they have been uploaded. --}}
@if (($letterhead['stamp'] ?? null) || ($letterhead['signature'] ?? null))
    <table class="issued-by">
        <tr>
            <td>
                <div>For {{ $letterhead['legal_name'] ?: $letterhead['organisation'] }}:</div>
                @if ($letterhead['signature'])
                    <div><img class="signature" src="{{ $letterhead['signature'] }}" alt="Signature"></div>
                @else
                    <div style="height: 16mm;"></div>
                @endif
                <div class="line">
                    @if ($letterhead['signatory_name'])<strong>{{ $letterhead['signatory_name'] }}</strong>@else Authorised signatory @endif
                    @if ($letterhead['signatory_title'])<div class="meta">{{ $letterhead['signatory_title'] }}</div>@endif
                </div>
            </td>
            <td style="text-align: right;">
                @if ($letterhead['stamp'])
                    <img class="stamp" src="{{ $letterhead['stamp'] }}" alt="Official stamp">
                @endif
            </td>
        </tr>
    </table>
@endif
