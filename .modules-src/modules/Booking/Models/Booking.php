<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Booking\Database\Factories\BookingFactory;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses that occupy the slot. A cancelled booking frees it. */
    public const HOLDING = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

    protected $fillable = [
        'bookable_resource_id', 'user_id', 'reference', 'name', 'email',
        'notes', 'starts_at', 'ends_at', 'status', 'cancelled_at',
    ];

    protected $casts = [
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<BookableResource, self> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResource::class, 'bookable_resource_id');
    }

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Bookings that overlap a window.
     *
     * Half-open comparison — a booking ending exactly when another starts does
     * NOT overlap. Using >= on both sides makes every back-to-back slot look
     * like a conflict and silently halves a day's capacity.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, Carbon $start, Carbon $end): void
    {
        $query->whereIn('status', self::HOLDING)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeHolding(Builder $query): void
    {
        $query->whereIn('status', self::HOLDING);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    protected static function booted(): void
    {
        static::creating(function (self $booking) {
            // NOT Str::upper(Str::random(8)). Str::random draws from 62
            // symbols, and uppercasing folds it onto 36 NON-uniformly — a
            // letter lands twice as often as a digit — which costs about 7 bits
            // and leaves ~41, not the 48 the length suggests. That matters here
            // because the reference is the credential for the public lookup and
            // one half of the credential for cancelling.
            $booking->reference ??= mb_strtoupper(bin2hex(random_bytes(5)));
        });
    }

    protected static function newFactory(): Factory
    {
        return BookingFactory::new();
    }
}
