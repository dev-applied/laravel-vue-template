<x-mail::message>
# New support request

**Reference:** {{ $ticket->reference }}
**From:** {{ $ticket->name }} &lt;{{ $ticket->email }}&gt;
**Subject:** {{ $ticket->subject }}

{{ $ticket->body }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
