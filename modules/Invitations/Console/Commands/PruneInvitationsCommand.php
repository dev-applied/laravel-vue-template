<?php

declare(strict_types=1);

namespace Modules\Invitations\Console\Commands;

use Illuminate\Console\Command;
use Modules\Invitations\Models\Invitation;

class PruneInvitationsCommand extends Command
{
    protected $signature = 'invitations:prune {--days=30 : Delete spent invitations older than this}';

    protected $description = 'Delete accepted, revoked and expired invitations past the retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $deleted = Invitation::query()
            ->where('created_at', '<', $cutoff)
            ->where(fn ($q) => $q
                ->whereNotNull('accepted_at')
                ->orWhereNotNull('revoked_at')
                ->orWhere('expires_at', '<', now()))
            ->delete();

        $this->components->info("Pruned {$deleted} spent invitations older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
