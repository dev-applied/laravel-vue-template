<?php

declare(strict_types=1);

namespace Modules\Support\Console\Commands;

use Illuminate\Console\Command;
use Modules\Support\Models\SupportTicket;

class PruneSpamCommand extends Command
{
    protected $signature = 'support:prune-spam {--days=30 : Delete spam-flagged tickets older than this}';

    protected $description = 'Delete tickets flagged as spam past the retention window';

    public function handle(): int
    {
        $cutoff  = now()->subDays((int) $this->option('days'));
        $deleted = SupportTicket::query()->where('is_spam', true)->where('created_at', '<', $cutoff)->delete();

        $this->components->info("Pruned {$deleted} spam tickets older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
