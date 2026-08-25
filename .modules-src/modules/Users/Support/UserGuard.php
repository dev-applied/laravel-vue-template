<?php

declare(strict_types=1);

namespace Modules\Users\Support;

use App\Exceptions\AppException;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The protections that stop an admin locking everyone out.
 *
 * Every one of these is a real incident somebody has had: the admin who
 * deactivated themselves and could not get back in, and the admin who deleted
 * the only other admin and left an org with nobody who could add one.
 */
class UserGuard
{
    public function assertNotSelf(User $actor, User $subject, string $action): void
    {
        if ($actor->getKey() === $subject->getKey()) {
            throw new AppException("You cannot {$action} your own account.", 422);
        }
    }

    /**
     * Refuse to remove the last user who can still sign in.
     *
     * Counted over ACTIVE users rather than all of them, because a deactivated
     * account cannot let anyone back in.
     */
    public function assertNotLastActive(User $subject): void
    {
        // lockForUpdate, and the CALLER must already be in a transaction — see
        // `protecting()`. Counting and then acting is the exact shape this
        // guard exists to prevent: two admins are the only active accounts and
        // each deactivates the other at the same moment, both queries return
        // "1 remaining", both pass, both writes land, and there are now zero
        // active users. There is no in-app recovery from that — role management
        // sits behind an account nobody can sign into, so it takes shell access
        // or direct SQL. "Two admins removing each other during a handover" is
        // a real thing that happens.
        $remaining = User::query()
            ->whereKeyNot($subject->getKey())
            ->whereNull('deactivated_at')
            ->lockForUpdate()
            ->count();

        if ($remaining === 0) {
            throw new AppException(
                'This is the last active account — removing it would lock everyone out.',
                422
            );
        }
    }

    /**
     * Run a last-active-guarded change inside one transaction.
     *
     * The lock in `assertNotLastActive` is only worth anything while a
     * transaction is open — outside one it is taken and released before the
     * write, which is no protection at all. This exists so a caller cannot
     * forget.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $change
     * @return TReturn
     */
    public function protecting(User $subject, Closure $change): mixed
    {
        return DB::transaction(function () use ($subject, $change) {
            $this->assertNotLastActive($subject);

            return $change();
        });
    }
}
