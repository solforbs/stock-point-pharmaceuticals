Hello {{ $tenantRequest->contact_name }},

Thank you for your interest in {{ config('app.name') }} for {{ $tenantRequest->institution_name }}.
We are unable to set up an account at this time.

@if ($tenantRequest->rejection_reason)
Reason: {{ $tenantRequest->rejection_reason }}

@endif
You are welcome to contact us if anything has changed.

This is an automated message; replies are not monitored.
