<x-mail::message>
    <p>Hello {{ $user['first_name'] }},</p>
    <p>Please reset your password by clicking the button below.</p>
    <x-mail::button :url="url('set-password', $token).'?email='.urlencode($user['email'])" color="primary">
        Set Password
    </x-mail::button>
    <p>Thanks you</p>
</x-mail::message>
