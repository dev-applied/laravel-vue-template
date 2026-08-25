<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (provider, subject) a user has signed in with.
 *
 * A link TABLE rather than columns on `users`: a user can legitimately hold
 * more than one provider identity (Google at work, Microsoft after an
 * acquisition), and columns force one.
 */
class UserSsoIdentity extends Model
{
    protected $table = 'user_sso_identities';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'email',
        'last_login_at',
        // SAML Single Logout only — see the 000100 migration.
        'name_id',
        'name_id_format',
        'session_index',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['last_login_at' => 'datetime'];
    }
}
