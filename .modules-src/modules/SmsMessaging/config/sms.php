<?php

declare(strict_types=1);

return [
    // 'log' (default) or 'twilio'. See ModuleServiceProvider for why log is the
    // default rather than a placeholder vendor.
    'driver' => env('SMS_DRIVER', 'log'),

    // Used by PhoneNumber to complete a bare national number. Everything is
    // stored as E.164 so the opt-out list can actually match a send.
    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '1'),

    'twilio' => [
        'sid' => env('TWILIO_SID', ''),
        'token' => env('TWILIO_TOKEN', ''),
        'from' => env('TWILIO_FROM', ''),
    ],

    // Carrier-required keyword replies. HELP must say how to opt out.
    'help_reply' => env('SMS_HELP_REPLY', 'Reply STOP to opt out.'),
    'start_reply' => env('SMS_START_REPLY', 'You are re-subscribed. Reply STOP to opt out.'),

    // Used by the Otp bridge when both modules are installed.
    'otp_template' => env('SMS_OTP_TEMPLATE', 'Your verification code is :code'),
];
