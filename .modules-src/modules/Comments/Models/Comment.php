<?php

declare(strict_types=1);

namespace Modules\Comments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Comments\Database\Factories\CommentFactory;
use Modules\Comments\Support\MentionParser;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = ['commentable_type', 'commentable_id', 'user_id', 'parent_id', 'body', 'is_internal', 'edited_at'];

    protected $casts = [
        'is_internal' => 'boolean',
        'edited_at'   => 'datetime',
    ];

    /** @return MorphTo<Model, self> */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, self> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<self, self> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, self> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest('id');
    }

    /** @return BelongsToMany<User, self> */
    public function mentions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comment_mentions')->withTimestamps();
    }

    /**
     * Comments this user is allowed to see. An internal note is staff-only —
     * the default is that nobody sees them, and the project opens the door by
     * defining the `see-internal-comments` ability.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, bool $canSeeInternal): void
    {
        if (! $canSeeInternal) {
            $query->where('is_internal', false);
        }
    }

    public function plainBody(): string
    {
        return app(MentionParser::class)->toPlainText($this->body);
    }

    protected static function newFactory(): Factory
    {
        return CommentFactory::new();
    }
}
