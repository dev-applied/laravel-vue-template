<?php

declare(strict_types=1);

namespace Modules\Invitations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Invitations\Http\Requests\StoreInvitationRequest;
use Modules\Invitations\Http\Resources\InvitationResource;
use Modules\Invitations\Mail\InvitationMail;
use Modules\Invitations\Models\Invitation;

/**
 * Admin side of the flow. Gated by the `invitations.manage` permission when the
 * RolesPermissions module is installed; falls back to "any authenticated user"
 * when it is not, because a module may not assume another module exists.
 */
class InvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invitations = Invitation::query()
            ->with('inviter')
            ->latest('id')
            ->vuetifyPaginate();

        $invitations->setCollection(
            $invitations->getCollection()->map(fn (Invitation $i) => new InvitationResource($i))->collect()
        );

        return response()->json($invitations);
    }

    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $email = mb_strtolower($request->string('email')->toString());

        // Do NOT validate the email as unique:users and do NOT report that it
        // is taken: that turns this endpoint into a membership oracle. Silently
        // no-op instead, returning the same shape either way.
        if (User::query()->where('email', $email)->exists()) {
            return response()->json(['invitation' => null, 'sent' => true], 202);
        }

        // Same transaction as resend(), for the same reason: issue() revokes
        // any pending invitation for this address first, so a failed send here
        // would destroy a working link and put nothing in its place. Inviting
        // someone twice is a normal thing to do by accident.
        $invitation = DB::transaction(function () use ($request, $email) {
            $invitation = Invitation::issue(
                $email,
                $request->string('role')->toString() ?: null,
                $request->user()?->getKey(),
            );

            Mail::to($email)->send(new InvitationMail(
                $invitation,
                (string) $invitation->plain_token,
                mb_trim(($request->user()?->first_name ?? '').' '.($request->user()?->last_name ?? '')) ?: null,
            ));

            return $invitation;
        });

        return response()->json([
            'invitation' => new InvitationResource($invitation),
            'sent'       => true,
        ], 201);
    }

    /** Re-issues the token, which invalidates the previous link. */
    public function resend(Request $request, Invitation $invitation): JsonResponse
    {
        // issue() revokes every pending invitation for the address before it
        // mints a new one — correct, since two live tokens for one person is a
        // second way in that nobody is tracking. But the send happened after
        // it, outside any transaction: an SMTP failure left the original
        // invitation revoked and the replacement token undelivered, so the
        // invitee's working link was destroyed by a resend that never arrived.
        // Rolling back returns them to exactly where they were.
        $reissued = DB::transaction(function () use ($request, $invitation) {
            $reissued = Invitation::issue(
                $invitation->email,
                $invitation->role,
                $request->user()?->getKey(),
            );

            Mail::to($reissued->email)->send(new InvitationMail(
                $reissued,
                (string) $reissued->plain_token,
                mb_trim(($request->user()?->first_name ?? '').' '.($request->user()?->last_name ?? '')) ?: null,
            ));

            return $reissued;
        });

        return response()->json(['invitation' => new InvitationResource($reissued)]);
    }

    public function destroy(Invitation $invitation): JsonResponse
    {
        // Revoke rather than delete: the audit question "who was invited and
        // then un-invited" is worth being able to answer.
        $invitation->update(['revoked_at' => now()]);

        return response()->json(['invitation' => new InvitationResource($invitation->fresh())]);
    }
}
