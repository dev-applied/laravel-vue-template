<?php

declare(strict_types=1);

namespace Modules\Support\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Support\Models\SupportTicket;
use Modules\Support\Models\TicketReply;

class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly TicketReply $reply,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Re: [{$this->ticket->reference}] {$this->ticket->subject}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'support::mail.ticket-reply');
    }
}
