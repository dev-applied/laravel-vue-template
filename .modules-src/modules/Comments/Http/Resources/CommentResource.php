<?php

declare(strict_types=1);

namespace Modules\Comments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Comments\Models\Comment;

/**
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id'         => $this->id,
            'body'       => $this->body,
            'isInternal' => $this->is_internal,
            'parentId'   => $this->parent_id,
            // NOT whenLoaded(): Laravel returns MissingValue when a loaded
            // relation is null, which would drop the author key entirely for a
            // deleted account — and the thread still has to make sense.
            'author' => $this->when($this->relationLoaded('author'), fn () => [
                'id'   => $this->author?->getKey(),
                'name' => $this->authorName(),
            ]),
            'mentions' => $this->whenLoaded('mentions', fn () => $this->mentions->map(fn ($u) => [
                'id'   => $u->getKey(),
                'name' => mb_trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: ($u->name ?? null),
            ])->values()),
            'replies' => self::collection($this->whenLoaded('replies')),
            // Drives whether the UI offers edit/delete, so it never offers and
            // then 403s.
            'canEdit'   => $user !== null && (int) $this->user_id === (int) $user->getKey(),
            'editedAt'  => $this->edited_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    private function authorName(): string
    {
        $author = $this->author;

        if ($author === null) {
            // The account was deleted; the comment survives because the thread
            // still has to make sense without it.
            return 'Deleted user';
        }

        return mb_trim(($author->first_name ?? '').' '.($author->last_name ?? ''))
            ?: ($author->name ?? $author->email ?? 'Unknown');
    }
}
