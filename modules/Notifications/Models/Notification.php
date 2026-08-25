<?php

declare(strict_types=1);

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification;
use Modules\Notifications\Database\Factories\NotificationFactory;

/**
 * Laravel's DatabaseNotification with the query surface this module's API needs.
 *
 * The payload lives in the free-form `data` column. This module's convention —
 * what NotificationResource reads and the bell renders — is:
 *
 *   ['title' => string, 'body' => ?string, 'icon' => ?string,
 *    'color' => ?string, 'url'  => ?string]
 *
 * @property-read array<string, mixed> $data
 */
class Notification extends DatabaseNotification
{
    use HasFactory;

    /** @param  Builder<self>  $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /** @param  Builder<self>  $query */
    public function scopeForNotifiable(Builder $query, string $type, int|string $id): void
    {
        $query->where('notifiable_type', $type)->where('notifiable_id', $id);
    }

    protected static function newFactory(): Factory
    {
        return NotificationFactory::new();
    }
}
