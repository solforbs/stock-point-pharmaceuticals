Hello,

@if ($invitation->purpose === 'REGISTER')
Your request has been approved. Use the link below to set up your institution
on {{ config('app.name') }} and start your free {{ \App\Models\Organisation::TRIAL_DAYS }}-day trial:
@else
An account has been created for you on {{ config('app.name') }}
@if ($invitation->organisation)
for {{ $invitation->organisation->name }}.
@endif
Use the link below to choose your password and sign in with {{ $invitation->email }}:
@endif

{{ $link }}

This link works once: as soon as it is opened it cannot be used again, and the
form it opens stays valid for {{ \App\Models\TenantInvitation::SESSION_MINUTES }} minutes. It expires unopened on
{{ $invitation->expires_at->format('j M Y') }}. If it stops working, ask us for a new one.

This is an automated message; replies are not monitored.
