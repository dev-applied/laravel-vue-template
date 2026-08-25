<?php

declare(strict_types=1);

namespace Modules\Comments\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Comments\Models\Comment;

/**
 * Fired once per newly-mentioned user.
 *
 * An event rather than a notification on purpose: this module must not assume
 * the Notifications module is installed, or that a project wants a database
 * notification rather than an email or a Slack post. A project listens and
 * decides.
 *
 *   Event::listen(UserMentioned::class, function ($event) {
 *       $event->user->notify(new MentionedInComment($event->comment));
 *   });
 */
class UserMentioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Comment $comment,
    ) {}
}
