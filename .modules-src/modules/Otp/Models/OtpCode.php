<?php

declare(strict_types=1);

namespace Modules\Otp\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = [
        'identifier', 'channel', 'purpose', 'code_hash',
        'attempts', 'expires_at', 'consumed_at', 'ip_address',
    ];

    protected $casts = [
        'attempts'    => 'integer',
        'expires_at'  => 'datetime',
        'consumed_at' => 'datetime',
    ];

    /**
     * Codes still usable: not consumed, not expired, attempts left.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', config('otp.max_attempts', 5));
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
