<?php

declare(strict_types=1);

namespace Modules\Tasks\Models;

use App\Models\User;
use App\Traits\WhoDidIt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Tasks\Database\Factories\TaskFactory;

class Task extends Model
{
    use HasFactory, WhoDidIt;

    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_TODO, self::STATUS_IN_PROGRESS, self::STATUS_BLOCKED,
        self::STATUS_DONE, self::STATUS_CANCELLED,
    ];

    /** Terminal states. Nothing moves out of these except by reopening. */
    public const CLOSED = [self::STATUS_DONE, self::STATUS_CANCELLED];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'title', 'description', 'status', 'priority', 'assigned_to',
        'due_at', 'completed_at', 'taskable_type', 'taskable_id', 'position',
    ];

    /**
     * Mirrors the column defaults in memory.
     *
     * Without this a freshly created Task has a null status until it is
     * re-read: Eloquent does not fetch database defaults back after an insert,
     * so anything reading ->status on the returned model gets null.
     */
    protected $attributes = [
        'status'   => self::STATUS_TODO,
        'priority' => 'normal',
        'position' => 0,
    ];

    protected $casts = [
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
        'position'     => 'integer',
    ];

    /** @return BelongsTo<User, self> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return MorphTo<Model, self> */
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isClosed(): bool
    {
        return in_array((string) $this->status, self::CLOSED, true);
    }

    public function isOverdue(): bool
    {
        // A closed task is never overdue, however late it was finished —
        // otherwise a "done" column full of red is the permanent state and
        // people stop reading the colour.
        return ! $this->isClosed()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('status', self::CLOSED);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeAssignedTo(Builder $query, User|int|null $user): void
    {
        if ($user === null) {
            return;
        }

        $query->where('assigned_to', $user instanceof User ? $user->getKey() : $user);
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, $v) => $q->where('priority', $v))
            ->when($filters['assigned_to'] ?? null, fn (Builder $q, $v) => $q->where('assigned_to', $v))
            ->when($filters['search'] ?? null, fn (Builder $q, $v) => $q->where('title', 'like', '%'.$v.'%'))
            ->when(($filters['open'] ?? null) !== null && $filters['open'], fn (Builder $q) => $q->open())
            ->when(($filters['overdue'] ?? null) !== null && $filters['overdue'], fn (Builder $q) => $q->overdue());
    }

    protected static function newFactory(): Factory
    {
        return TaskFactory::new();
    }
}
