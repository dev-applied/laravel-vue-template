<x-mail::message>
# Re: {{ $ticket->subject }}

{{ $reply->body }}

---

Reference **{{ $ticket->reference }}**. Reply to this email to continue the conversation.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
