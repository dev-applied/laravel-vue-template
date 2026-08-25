<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use App\Traits\WhoDidIt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Booking\Database\Factories\BookableResourceFactory;

class BookableResource extends Model
{
    use HasFactory, WhoDidIt;

    protected $fillable = [
        'name', 'slug', 'description', 'timezone', 'slot_minutes',
        'buffer_minutes', 'capacity', 'min_notice_minutes', 'advance_days', 'is_active',
    ];

    protected $casts = [
        'slot_minutes'       => 'integer',
        'buffer_minutes'     => 'integer',
        'capacity'           => 'integer',
        'min_notice_minutes' => 'integer',
        'advance_days'       => 'integer',
        'is_active'          => 'boolean',
    ];

    protected $attributes = [
        'timezone'           => 'UTC',
        'slot_minutes'       => 30,
        'buffer_minutes'     => 0,
        'capacity'           => 1,
        'min_notice_minutes' => 0,
        'advance_days'       => 60,
        'is_active'          => true,
    ];

    /** @return HasMany<ResourceAvailability, self> */
    public function availability(): HasMany
    {
        return $this->hasMany(ResourceAvailability::class);
    }

    /** @return HasMany<ResourceBlackout, self> */
    public function blackouts(): HasMany
    {
        return $this->hasMany(ResourceBlackout::class);
    }

    /** @return HasMany<Booking, self> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    protected static function newFactory(): Factory
    {
        return BookableResourceFactory::new();
    }
}
