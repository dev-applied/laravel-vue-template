<?php

declare(strict_types=1);

namespace Modules\Comments\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Comments\Events\UserMentioned;
use Modules\Comments\Http\Requests\StoreCommentRequest;
use Modules\Comments\Http\Resources\CommentResource;
use Modules\Comments\Models\Comment;
use Modules\Comments\Support\CommentableRegistry;
use Modules\Comments\Support\MentionParser;

class CommentController extends Controller
{
    public function __construct(
        private readonly CommentableRegistry $registry,
        private readonly MentionParser $parser,
    ) {}

    public function index(Request $request, string $type, int $id): JsonResponse
    {
        $record = $this->resolve($request, $type, $id);

        $comments = Comment::query()
            ->where('commentable_type', $record->getMorphClass())
            ->where('commentable_id', $record->getKey())
            ->whereNull('parent_id')
            ->visibleTo($this->canSeeInternal($request))
            ->with(['author', 'mentions', 'replies' => fn ($q) => $q->visibleTo($this->canSeeInternal($request))->with(['author', 'mentions'])])
            ->oldest('id')
            ->get();

        return response()->json(['comments' => CommentResource::collection($comments)]);
    }

    public function store(StoreCommentRequest $request, string $type, int $id): JsonResponse
    {
        $record = $this->resolve($request, $type, $id);

        $internal = $request->boolean('is_internal');

        // Writing an internal note needs the same ability as reading one —
        // otherwise anyone could file a note they then cannot see, and staff
        // would read input from someone who was never meant to write there.
        if ($internal && ! $this->canSeeInternal($request)) {
            throw new AppException('You cannot post internal notes.', 403);
        }

        $comment = DB::transaction(function () use ($request, $record, $internal) {
            $parentId = $this->validParentId($request, $record);

            $comment = Comment::create([
                'commentable_type' => $record->getMorphClass(),
                'commentable_id'   => $record->getKey(),
                'user_id'          => $request->user()->getKey(),
                'parent_id'        => $parentId,
                'body'             => $request->string('body')->toString(),
                'is_internal'      => $internal,
            ]);

            $this->syncMentions($comment, $request->user()->getKey());

            return $comment;
        });

        return response()->json(
            new CommentResource($comment->load(['author', 'mentions'])),
            201
        );
    }

    public function update(StoreCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->assertCanReachParent($request, $comment);
        $this->assertOwn($request, $comment);

        DB::transaction(function () use ($request, $comment) {
            $comment->update([
                'body' => $request->string('body')->toString(),
                // Stamped so the UI can mark it edited — a comment that
                // silently changed after someone replied to it is worse than
                // one that says it changed.
                'edited_at' => now(),
            ]);

            $this->syncMentions($comment, $request->user()->getKey());
        });

        return response()->json(new CommentResource($comment->fresh()->load(['author', 'mentions'])));
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->assertCanReachParent($request, $comment);
        $this->assertOwn($request, $comment);

        // Replies cascade — a reply to nothing reads as a non-sequitur.
        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }

    /**
     * Resolve the target record through the allow-list, then run the project's
     * own ability against it. Anything unregistered is rejected outright.
     */
    private function resolve(Request $request, string $type, int $id): Model
    {
        if (! $this->registry->has($type)) {
            throw new AppException("Comments are not enabled for [{$type}].", 404);
        }

        ['model' => $model, 'ability' => $ability] = $this->registry->get($type);

        /** @var Model|null $record */
        $record = $model::query()->find($id);

        if ($record === null) {
            throw new AppException('Record not found.', 404);
        }

        // 404, with the same message a missing record gets. A 403 here would
        // confirm the record EXISTS, and ids are sequential — probing
        // /comments/orders/1..N and sorting 403 from 404 enumerates the whole
        // table without ever reading a row.
        if ($ability !== null && ! Gate::forUser($request->user())->allows($ability, $record)) {
            throw new AppException('Record not found.', 404);
        }

        return $record;
    }

    private function canSeeInternal(Request $request): bool
    {
        return Gate::forUser($request->user())->allows('see-internal-comments');
    }

    /**
     * Re-run the project's ability against the record the comment hangs off.
     *
     * Ownership was the only check on edit and delete, and ownership does not
     * expire: someone who commented on an order and was later removed from the
     * account that owns it kept full write access to those comments. Editing
     * also re-syncs mentions, so they could go on notifying people inside a
     * record they can no longer open.
     *
     * Deliberately BEFORE the ownership check. Running it after would answer
     * "that comment belongs to someone else" for a record the caller cannot
     * reach, which is the same existence oracle in a different place. Once the
     * parent is reachable the caller can already list its comments, so a 403
     * from assertOwn() past this point reveals nothing new.
     */
    private function assertCanReachParent(Request $request, Comment $comment): void
    {
        $registered = $this->registry->forMorphClass((string) $comment->commentable_type);

        // Unregistered now, whatever it was when the comment was written —
        // a project that stopped exposing a type must stop exposing its
        // comments too.
        if ($registered === null) {
            throw new AppException('Record not found.', 404);
        }

        $record = $registered['model']::query()->find($comment->commentable_id);

        if ($record === null) {
            throw new AppException('Record not found.', 404);
        }

        if ($registered['ability'] !== null
            && ! Gate::forUser($request->user())->allows($registered['ability'], $record)) {
            throw new AppException('Record not found.', 404);
        }
    }

    private function assertOwn(Request $request, Comment $comment): void
    {
        if ((int) $comment->user_id !== (int) $request->user()->getKey()) {
            throw new AppException('That comment belongs to someone else.', 403);
        }
    }

    /**
     * A reply must belong to the same record and must not itself be a reply —
     * one level only. Anything else is silently flattened to a root comment
     * rather than rejected, because the caller's intent was clearly "comment
     * on this record".
     */
    private function validParentId(Request $request, Model $record): ?int
    {
        $parentId = $request->integer('parent_id') ?: null;

        if ($parentId === null) {
            return null;
        }

        $parent = Comment::find($parentId);

        $sameRecord = $parent !== null
            && $parent->commentable_type === $record->getMorphClass()
            && (int) $parent->commentable_id === (int) $record->getKey();

        return $sameRecord && $parent->parent_id === null ? $parentId : null;
    }

    /**
     * Fires UserMentioned once per NEWLY mentioned user.
     *
     * Editing a comment to fix a typo must not re-notify everyone already in
     * it — that is the behaviour that trains people to ignore mentions.
     */
    private function syncMentions(Comment $comment, int $authorId): void
    {
        $ids = array_values(array_diff($this->parser->extract($comment->body), [$authorId]));

        // Filter to real accounts before syncing. A typo'd or stale id would
        // otherwise hit the foreign key and 500 the whole comment — losing
        // what the person wrote over a bad mention.
        $ids = $ids === [] ? [] : User::query()->whereIn('id', $ids)->pluck('id')->all();

        $existing = $comment->mentions()->pluck('users.id')->all();
        $changes  = $comment->mentions()->sync($ids);

        foreach ($changes['attached'] as $attachedId) {
            if (in_array((int) $attachedId, array_map('intval', $existing), true)) {
                continue;
            }

            $user = $comment->mentions()->find($attachedId);

            if ($user !== null) {
                UserMentioned::dispatch($user, $comment);
            }
        }
    }
}
