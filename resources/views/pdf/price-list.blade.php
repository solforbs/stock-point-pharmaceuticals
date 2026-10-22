@extends('pdf.layout', ['title' => $title, 'docNumber' => 'PRICE LIST', 'docDate' => $date])

@section('body')
    <table class="items">
        <thead>
        <tr>
            @foreach ($columns as $column)
                <th @class(['num' => $column === 'price'])>{{ $labels[$column] }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                @foreach ($columns as $column)
                    @if ($column === 'price')
                        <td class="num">{{ $row['price'] === null || $row['price'] === '' ? '—' : number_format((float) $row['price'], 2) }}</td>
                    @else
                        <td>{{ $row[$column] ?? '' }}</td>
                    @endif
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

    <p class="meta" style="margin-top: 10px;">{{ count($rows) }} products. Prices in KES.</p>
@endsection
