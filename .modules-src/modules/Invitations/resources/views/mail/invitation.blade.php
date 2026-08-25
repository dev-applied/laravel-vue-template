<x-mail::message>
# You have been invited

@if ($inviterName)
{{ $inviterName }} has invited you to join **{{ config('app.name') }}**.
@else
You have been invited to join **{{ config('app.name') }}**.
@endif

Use the button below to create your account.

<x-mail::button :url="$url">
Accept invitation
</x-mail::button>

This link expires {{ $expiresAt->diffForHumans() }} and can only be used once.
If you were not expecting this invitation you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
