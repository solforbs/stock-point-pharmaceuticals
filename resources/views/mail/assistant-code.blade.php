Hello {{ $user->name }},

Someone asked the {{ config('app.name') }} assistant to answer questions as you.
Type this code into the chat box to go ahead:

{{ $code }}

The code works once and expires in {{ \App\Models\AssistantSession::CODE_MINUTES }} minutes. The session it opens
lasts {{ \App\Models\AssistantSession::SESSION_MINUTES }} minutes, can only read, and can only answer the fixed list
of questions the assistant offers — it cannot sell, post, edit or approve anything,
and it can never see more than your role already sees in the system.

@if ($session->ip)
The request came from {{ $session->ip }}.
@endif

If this was not you, ignore this message: without the code nothing opens. Tell your
administrator if it keeps happening, and change your password if you think someone
else is reading your email.

This is an automated message; replies are not monitored.
