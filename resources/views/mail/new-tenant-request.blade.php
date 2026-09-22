A new institution has asked for a quote.

Institution: {{ $tenantRequest->institution_name }}
Contact:     {{ $tenantRequest->contact_name }} <{{ $tenantRequest->email }}>
@if ($tenantRequest->phone)
Phone:       {{ $tenantRequest->phone }}
@endif
@if ($tenantRequest->town)
Town:        {{ $tenantRequest->town }}
@endif
@if ($tenantRequest->branches_count)
Branches:    {{ $tenantRequest->branches_count }}
@endif
@if ($tenantRequest->users_count)
Users:       {{ $tenantRequest->users_count }}
@endif
@if ($tenantRequest->message)

Message:
{{ $tenantRequest->message }}
@endif

Review it in the platform console: {{ url('/platform') }}
