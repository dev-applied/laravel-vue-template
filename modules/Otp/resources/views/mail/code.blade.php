<x-mail::message>
# Your verification code

<x-mail::panel>
{{ $code }}
</x-mail::panel>

It expires in {{ config('otp.ttl_minutes', 10) }} minutes and can only be used once.

If you did not request it, you can ignore this email — nobody can sign in with it unless they also have access to this inbox.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
