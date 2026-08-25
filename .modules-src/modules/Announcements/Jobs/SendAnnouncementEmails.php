<?php

declare(strict_types=1);

namespace Modules\Announcements\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Announcements\Mail\AnnouncementMail;
use Modules\Announcements\Models\Announcement;
use Modules\Announcements\Support\AudienceResolver;
use Throwable;

/**
 * Emails a published announcement to its resolved audience.
 *
 * Takes an id rather than the model: a serialized model in the payload can be
 * stale by the time the worker picks it up, and an announcement that was
 * unpublished in those thirty seconds must not still go out.
 */
class SendAnnouncementEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $announcementId) {}

    public function handle(AudienceResolver $resolver): void
    {
        $announcement = Announcement::find($this->announcementId);

        // Unpublished between dispatch and pickup — do not send.
        if ($announcement === null || $announcement->published_at === null) {
            return;
        }

        foreach ($resolver->audience($announcement) as $recipient) {
            $email = $recipient->email ?? null;

            if (! $email) {
                continue;
            }

            // Claim the address before sending to it. Without this a retry
            // restarts at the first recipient and mails everyone again — this
            // job's own comment used to say exactly that and then not act on
            // it — and two jobs from a double publish do the same thing.
            // insertOrIgnore leans on the unique index, so the database
            // decides, and two workers racing on the same address cannot both
            // win it.
            $claimed = DB::table('announcement_deliveries')->insertOrIgnore([
                'announcement_id' => $announcement->getKey(),
                'recipient'       => mb_strtolower((string) $email),
                'sent_at'         => now(),
            ]);

            if ($claimed === 0) {
                continue;
            }

            try {
                Mail::to($email)->send(new AnnouncementMail($announcement));
            } catch (Throwable $e) {
                // One bad address must not abandon the rest of the send.
                //
                // The claim is deliberately NOT released here. A send that
                // threw may still have been accepted upstream, and retrying it
                // risks a duplicate to fix a maybe-missed one; the recipient
                // sees the announcement in-app regardless.
                report($e);
            }
        }
    }
}
