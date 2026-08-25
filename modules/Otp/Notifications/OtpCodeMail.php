<?php

declare(strict_types=1);

namespace Modules\Otp\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose = 'login',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your verification code: '.$this->code);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'otp::mail.code',
            with: ['code' => $this->code, 'purpose' => $this->purpose],
        );
    }
}
