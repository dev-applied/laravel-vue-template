<?php

declare(strict_types=1);

namespace Modules\Support\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Support\Models\SupportTicket;

class TicketReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SupportTicket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->ticket->reference}] {$this->ticket->subject}",
            // So a staff reply from the mailbox goes to the requester, not to
            // the app's own from-address.
            replyTo: [$this->ticket->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'support::mail.ticket-received');
    }
}
