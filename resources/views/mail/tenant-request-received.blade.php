Hello {{ $tenantRequest->contact_name }},

Thank you for your interest in {{ config('app.name') }} for {{ $tenantRequest->institution_name }}.

We have received your request and will review it shortly. Once it is approved you
will receive an email with a link to set up your institution and start a free
{{ \App\Models\Organisation::TRIAL_DAYS }}-day trial.

This is an automated message; replies are not monitored.
