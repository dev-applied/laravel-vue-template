<?php

declare(strict_types=1);

namespace Modules\Invitations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Invitations\Database\Factories\InvitationFactory;

/**
 * @property-read string|null $plain_token only set on the instance that issued it
 */
class Invitation extends Model
{
    use HasFactory;

    /** Returned once, at issue time. Never persisted in the clear. */
    public ?string $plain_token = null;

    protected $fillable = [
        'email', 'token_hash', 'role', 'invited_by', 'user_id',
        'expires_at', 'accepted_at', 'revoked_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at'  => 'datetime',
    ];

    /** Hidden so a token hash never rides out in a JSON payload by accident. */
    protected $hidden = ['token_hash'];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Issue a fresh invitation, revoking any outstanding one for that email. */
    public static function issue(string $email, ?string $role, ?int $invitedBy, int $days = 7): self
    {
        self::query()->pending()->where('email', $email)->update(['revoked_at' => now()]);

        $token = Str::random(64);

        $invitation = self::create([
            'email'      => $email,
            'token_hash' => self::hashToken($token),
            'role'       => $role,
            'invited_by' => $invitedBy,
            'expires_at' => now()->addDays($days),
        ]);

        $invitation->plain_token = $token;

        return $invitation;
    }

    /**
     * Look up by the plaintext token. Matching on the HASH means this is an
     * indexed equality lookup rather than a row scan with a timing-sensitive
     * comparison — the hash of a wrong guess simply matches nothing.
     */
    public static function findByToken(string $token): ?self
    {
        return self::query()->where('token_hash', self::hashToken($token))->first();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    /** @param  Builder<self>  $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null  => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default                     => 'pending',
        };
    }

    /** @return BelongsTo<User, self> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    protected static function newFactory(): Factory
    {
        return InvitationFactory::new();
    }
}
