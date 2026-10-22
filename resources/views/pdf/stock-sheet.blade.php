@extends('pdf.layout', ['title' => $title, 'docNumber' => 'STOCK ON HAND', 'docDate' => $date])

@section('body')
    @if ($subtitle)<p class="meta">{{ $subtitle }}</p>@endif

    <table class="items">
        <thead>
        <tr>
            @foreach ($header as $i => $label)
                <th @class(['num' => in_array($i, [0, 6, 7], true)])>{{ $label }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($header as $i => $label)
                    <td @class(['num' => in_array($i, [0, 6, 7], true)])>{{ $row[$i] ?? '' }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($header) }}">No stock on hand for this selection.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="cols" style="margin-top: 28px;">
        <tr>
            <td>Counted by: ______________________</td>
            <td>Checked by: ______________________</td>
            <td>Date: ____________</td>
        </tr>
    </table>
@endsection
