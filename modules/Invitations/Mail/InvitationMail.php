<?php

declare(strict_types=1);

namespace Modules\Invitations\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Invitations\Models\Invitation;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $token,
        public readonly ?string $inviterName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You have been invited to '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'invitations::mail.invitation',
            with: [
                'url' => mb_rtrim((string) config('app.frontend_url', config('app.url')), '/')
                    .'/accept-invite?token='.$this->token,
                'expiresAt'   => $this->invitation->expires_at,
                'inviterName' => $this->inviterName,
            ],
        );
    }
}
