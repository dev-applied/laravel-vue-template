<?php

declare(strict_types=1);

namespace Modules\Otp\Console\Commands;

use Illuminate\Console\Command;
use Modules\Otp\Models\OtpCode;

/**
 * Expired codes are dead weight and, hashed or not, a record of who was trying
 * to sign in and when.
 */
class PruneOtpCodesCommand extends Command
{
    protected $signature = 'otp:prune {--days=7 : Delete codes that expired more than this many days ago}';

    protected $description = 'Delete expired one-time codes';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $deleted = OtpCode::query()->where('expires_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} expired code(s).");

        return self::SUCCESS;
    }
}
