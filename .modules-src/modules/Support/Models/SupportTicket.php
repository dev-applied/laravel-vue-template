<?php

declare(strict_types=1);

namespace Modules\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Modules\Support\Database\Factories\SupportTicketFactory;
use RuntimeException;

class SupportTicket extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_PENDING, self::STATUS_RESOLVED, self::STATUS_CLOSED];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'user_id', 'assigned_to', 'name', 'email', 'subject', 'body',
        'status', 'priority', 'reference', 'ip_address', 'is_spam', 'resolved_at',
    ];

    protected $casts = [
        'is_spam'     => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /**
     * A human-quotable handle with enough room not to collide.
     *
     * The previous version took `Str::random(8)`, truncated it to SIX, and
     * uppercased — folding 62 symbols onto 36 and leaving 36^6 ≈ 2.18e9. On a
     * `unique` column that is a birthday problem, not a comfortable margin:
     * roughly a 1% chance of a collision by 6,600 tickets and 50% by 55,000.
     * And a collision threw a raw QueryException from `store()`, which is the
     * PUBLIC contact form — the customer got a 500 and their message was never
     * recorded.
     *
     * Now 8 characters from a 32-symbol alphabet: 32^8 ≈ 1.1e12, a thousand
     * times the space, still shorter to read aloud than a UUID. The alphabet
     * keeps the original intent and makes it real by dropping the look-alikes
     * (I, L, O, U, 0, 1) rather than merely uppercasing — someone reading a
     * reference down a phone line cannot confuse O for 0.
     */
    public static function newReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTVWXYZ';
        $max      = mb_strlen($alphabet) - 1;
        $out      = '';

        for ($i = 0; $i < 8; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    /**
     * Create a ticket, regenerating the reference if the index rejects it.
     *
     * The index is what makes a reference unique; this makes losing that race
     * a retry rather than a 500 on a public form. Bounded, and anything that
     * is not a reference collision is re-thrown untouched.
     */
    public static function createWithReference(array $attributes): self
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return static::create($attributes);
            } catch (UniqueConstraintViolationException $e) {
                if (! str_contains(mb_strtolower($e->getMessage()), 'reference')) {
                    throw $e;
                }

                unset($attributes['reference']);
            }
        }

        throw new RuntimeException('Could not allocate a unique support ticket reference after 5 attempts.');
    }

    /** @return HasMany<TicketReply, self> */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->oldest('id');
    }

    /** @return BelongsTo<User, self> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @param Builder<self> $query @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['assigned_to'] ?? null, fn ($q, $v) => $q->where('assigned_to', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(
                fn ($sub) => $sub->where('subject', 'like', "%{$v}%")
                    ->orWhere('email', 'like', "%{$v}%")
                    ->orWhere('reference', $v)
            ))
            // Spam is excluded unless explicitly asked for; a queue full of it
            // is a queue nobody works.
            ->when(! ($filters['include_spam'] ?? false), fn ($q) => $q->where('is_spam', false));
    }

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $ticket) {
            $ticket->reference ??= self::newReference();
        });
    }

    protected static function newFactory(): Factory
    {
        return SupportTicketFactory::new();
    }
}
