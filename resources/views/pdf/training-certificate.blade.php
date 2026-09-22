@extends('pdf.layout', ['title' => $title, 'docNumber' => $reference, 'docDate' => $completedOn])

@section('body')
    <div style="text-align: center; margin-top: 40px;">
        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: .12em; color: #6b7280;">PharmaPoint Training Centre</div>
        <div style="font-size: 12px; margin-top: 28px;">This certifies that</div>
        <div style="font-size: 24px; font-weight: bold; color: #1e3a8a; margin-top: 10px;">{{ $trainee->name }}</div>
        @if ($trainee->username)
            <div class="meta" style="margin-top: 2px;">{{ $trainee->username }}</div>
        @endif
        <div style="font-size: 12px; margin-top: 24px;">has completed the training module</div>
        <div style="font-size: 18px; font-weight: bold; margin-top: 8px;">{{ $module['title'] }}</div>
        <div class="meta" style="margin-top: 4px;">{{ $module['audience'] }}</div>
        <div style="font-size: 12px; margin-top: 24px;">on {{ $completedOn }}</div>
    </div>

    <table class="items" style="width: 70%; margin: 36px auto 0;">
        <thead>
        <tr><th>Requirement</th><th class="num">Result</th></tr>
        </thead>
        <tbody>
        <tr><td>Lessons read</td><td class="num">{{ $summary['lessons_viewed'] }} of {{ $summary['lessons_total'] }}</td></tr>
        <tr>
            <td>Practice tasks done in the live system</td>
            <td class="num">{{ $summary['tasks_completed'] }} of {{ $summary['tasks_total'] }} ({{ $summary['tasks_verified'] }} verified by the system)</td>
        </tr>
        <tr><td>Knowledge check (pass mark {{ \App\Services\Training\TrainingCatalogue::PASS_MARK }}%)</td><td class="num">Best score {{ $summary['best_score'] }}%</td></tr>
        </tbody>
    </table>

    <table class="cols" style="margin-top: 70px;">
        <tr>
            <td style="text-align: center;">
                <div style="border-top: 1px solid #9ca3af; width: 60%; margin: 0 auto; padding-top: 4px;">Trainee</div>
            </td>
            <td style="text-align: center;">
                <div style="border-top: 1px solid #9ca3af; width: 60%; margin: 0 auto; padding-top: 4px;">Manager</div>
            </td>
        </tr>
    </table>

    <p class="note" style="text-align: center; margin-top: 30px;">
        Issued from the training records of {{ $letterhead['organisation'] }}. Certificate reference {{ $reference }}.
    </p>
@endsection
