<?php

declare(strict_types=1);

namespace Modules\Favorites\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One user starring one record.
 *
 * No `updated_at` semantics worth anything and no soft deletes: un-favouriting
 * is a delete, because "was starred once" is not information anyone asked to
 * keep, and retaining it would quietly turn a preference into a history.
 */
class Favorite extends Model
{
    protected $fillable = ['user_id', 'favoritable_type', 'favoritable_id'];

    public function favoritable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
