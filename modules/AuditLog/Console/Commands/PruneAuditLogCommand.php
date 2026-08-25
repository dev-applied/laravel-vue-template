<?php

declare(strict_types=1);

namespace Modules\AuditLog\Console\Commands;

use Illuminate\Console\Command;
use Modules\AuditLog\Models\AuditLog;

/**
 * Audit trails grow without bound and are the first table to become the
 * biggest in a client database. Schedule this.
 */
class PruneAuditLogCommand extends Command
{
    protected $signature = 'audit:prune {--days= : Delete entries older than this many days (default AUDIT_RETENTION_DAYS or 365)}';

    protected $description = 'Delete audit log entries past the retention window';

    public function handle(): int
    {
        $days   = (int) ($this->option('days') ?: env('AUDIT_RETENTION_DAYS', 365));
        $cutoff = now()->subDays($days);

        // Chunked so pruning a very large table does not hold one enormous
        // delete transaction open.
        $deleted = 0;

        do {
            $batch = AuditLog::query()->where('created_at', '<', $cutoff)->limit(1000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->components->info("Pruned {$deleted} audit entries older than {$days} days ({$cutoff->toDateString()}).");

        return self::SUCCESS;
    }
}
