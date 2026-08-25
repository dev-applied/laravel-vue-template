<?php

declare(strict_types=1);

namespace Modules\Comments\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Comments\Models\Comment;

/**
 * Add to any model that should accept comments.
 *
 *   class Order extends Model
 *   {
 *       use HasComments;
 *   }
 */
trait HasComments
{
    /** @return MorphMany<Comment, self> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Top-level comments only, replies eager-loaded. Two queries rather than
     * one per comment.
     *
     * @return MorphMany<Comment, self>
     */
    public function rootComments(): MorphMany
    {
        return $this->comments()->whereNull('parent_id');
    }
}
