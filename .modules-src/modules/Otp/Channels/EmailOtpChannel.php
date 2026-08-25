<?php

declare(strict_types=1);

namespace Modules\Otp\Channels;

use Illuminate\Support\Facades\Mail;
use Modules\Otp\Notifications\OtpCodeMail;

class EmailOtpChannel implements OtpChannel
{
    public function send(string $identifier, string $code, string $purpose): void
    {
        Mail::to($identifier)->send(new OtpCodeMail($code, $purpose));
    }

    public function supports(string $identifier): bool
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function mask(string $identifier): string
    {
        [$local, $domain] = array_pad(explode('@', $identifier, 2), 2, '');

        if ($domain === '') {
            return str_repeat('•', mb_strlen($identifier));
        }

        $visible = mb_substr($local, 0, 1);

        return $visible.str_repeat('•', max(1, mb_strlen($local) - 1)).'@'.$domain;
    }
}
