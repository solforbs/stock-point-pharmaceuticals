@extends('pdf.layout', ['title' => $title, 'docNumber' => $docNumber, 'docDate' => $docDate])

@section('body')
    <table class="items" style="margin-top: 0;">
        <tr>
            <th style="width: 25%;">Document number</th><td>{{ $document->code }}</td>
            <th style="width: 25%;">Version</th><td>{{ $version }}</td>
        </tr>
        <tr>
            <th>Effective date</th><td>{{ date('j M Y', strtotime($effectiveDate)) }}</td>
            <th>Review due</th><td>{{ $document->review_due_date?->format('j M Y') ?? 'One year from effective date' }}</td>
        </tr>
        <tr>
            <th>Category</th><td>{{ $document->category }}</td>
            <th>Status</th><td>{{ $document->status === 'ACTIVE' ? 'Approved' : 'Draft for review' }}</td>
        </tr>
    </table>

    @foreach ($labels as $key => $label)
        @if (trim($sections[$key] ?? '') !== '')
            <h2 style="margin-top: 14px; color: #1e3a8a;">{{ $loop->iteration }}. {{ $label }}</h2>
            @php($lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $sections[$key])))))
            @if ($key === 'procedure' && count($lines) > 1)
                <ol style="margin: 4px 0 0 16px; padding: 0;">
                    @foreach ($lines as $line)<li style="margin-bottom: 3px;">{{ $line }}</li>@endforeach
                </ol>
            @elseif (count($lines) > 1)
                <ul style="margin: 4px 0 0 16px; padding: 0;">
                    @foreach ($lines as $line)<li style="margin-bottom: 2px;">{{ $line }}</li>@endforeach
                </ul>
            @else
                <p style="margin: 4px 0 0;">{{ $lines[0] ?? '' }}</p>
            @endif
        @endif
    @endforeach

    <table class="items" style="margin-top: 22px;">
        <tr><th style="width: 22%;"></th><th>Name</th><th>Signature</th><th style="width: 18%;">Date</th></tr>
        <tr><td>Prepared by</td><td style="height: 26px;"></td><td></td><td></td></tr>
        <tr><td>Reviewed by</td><td style="height: 26px;"></td><td></td><td></td></tr>
        <tr><td>Approved by (superintendent pharmacist)</td><td style="height: 26px;"></td><td></td><td></td></tr>
    </table>
@endsection
