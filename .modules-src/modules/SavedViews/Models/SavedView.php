<?php

declare(strict_types=1);

namespace Modules\SavedViews\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SavedViews\Database\Factories\SavedViewFactory;

class SavedView extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'key', 'name', 'payload', 'is_default', 'is_shared', 'position'];

    protected $casts = [
        'payload'    => 'array',
        'is_default' => 'boolean',
        'is_shared'  => 'boolean',
        'position'   => 'integer',
    ];

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Everything this user may see for one screen: their own views plus
     * anything a colleague shared for the same screen.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, string $key): void
    {
        $query->where('key', $key)
            ->where(function (Builder $q) use ($user) {
                $q->where('user_id', $user->getKey())->orWhere('is_shared', true);
            });
    }

    /**
     * A shared view someone else owns is read-only. Applying it is fine;
     * renaming or deleting it is not — one person tidying their own picker
     * must not silently change everyone else's.
     */
    public function isEditableBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->getKey();
    }

    protected static function newFactory(): Factory
    {
        return SavedViewFactory::new();
    }
}
