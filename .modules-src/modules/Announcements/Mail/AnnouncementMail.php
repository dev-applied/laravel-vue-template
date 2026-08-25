<?php

declare(strict_types=1);

namespace Modules\Announcements\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Announcements\Models\Announcement;

class AnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Announcement $announcement) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->announcement->title);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'announcements::mail.announcement',
            with: ['announcement' => $this->announcement],
        );
    }
}
