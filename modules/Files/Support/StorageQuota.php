<?php

declare(strict_types=1);

namespace Modules\Files\Support;

use Modules\Files\Models\File;

/**
 * Per-uploader storage cap.
 *
 * There has always been a 20MB per-FILE limit and nothing aggregate, so any
 * signed-in user could fill the disk one 20MB file at a time. On
 * `storage=s3-presigned` that stops being a disk problem and becomes a bill.
 *
 * Off by default (`null`), because a cap that appears on install would break
 * every project that never asked for one. Publish `config/files.php`:
 *
 *     return ['quota_mb' => 500];
 */
class StorageQuota
{
    /**
     * Kilobytes already stored for this uploader.
     *
     * `files.size` is kilobytes, not bytes — File::upload divides by 1000 — so
     * everything here stays in kilobytes and only the config is megabytes.
     */
    public function usedKb(int|string|null $userId): int
    {
        if ($userId === null) {
            return 0;
        }

        return (int) File::query()
            ->where((new File)->getCreatedByColumn(), $userId)
            ->sum('size');
    }

    /** Null when no quota is configured. */
    public function limitKb(): ?int
    {
        $mb = config('files.quota_mb');

        return $mb === null ? null : (int) $mb * 1000;
    }

    /**
     * The reason to refuse, or null to allow.
     */
    public function refuse(int|string|null $userId, int $incomingKb): ?string
    {
        $limit = $this->limitKb();

        if ($limit === null || $userId === null) {
            return null;
        }

        if ($this->usedKb($userId) + $incomingKb <= $limit) {
            return null;
        }

        return 'That upload would put you over your '.(int) config('files.quota_mb').' MB storage limit.';
    }
}
