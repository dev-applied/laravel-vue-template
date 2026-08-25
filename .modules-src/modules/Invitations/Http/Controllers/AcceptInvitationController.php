<?php

declare(strict_types=1);

namespace Modules\Invitations\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Invitations\Http\Requests\AcceptInvitationRequest;
use Modules\Invitations\Models\Invitation;

/**
 * Public half — the invitee is by definition not authenticated yet.
 *
 * The account is created on ACCEPT, not on send. Creating it up front leaves
 * dormant rows that can be enumerated and password-reset into, and makes
 * "invited" and "registered" indistinguishable.
 */
class AcceptInvitationController extends Controller
{
    /** Lets the accept page show who was invited before asking for a password. */
    public function show(Request $request): JsonResponse
    {
        $invitation = Invitation::findByToken((string) $request->query('token', ''));

        if (! $invitation?->isPending()) {
            // One message for missing, expired, revoked and already-accepted:
            // distinguishing them tells a guesser which tokens are real.
            return response()->json(['valid' => false, 'message' => 'This invitation link is no longer valid.'], 404);
        }

        return response()->json(['valid' => true, 'email' => $invitation->email]);
    }

    /** @throws AppException */
    public function store(AcceptInvitationRequest $request): JsonResponse
    {
        $invitation = Invitation::findByToken($request->string('token')->toString());

        if (! $invitation?->isPending()) {
            throw new AppException('This invitation link is no longer valid.', 404);
        }

        $user = DB::transaction(function () use ($invitation, $request): User {
            // Re-check inside the transaction with a row lock: two submissions
            // of the same link would otherwise both pass the check above and
            // create two accounts.
            $locked = Invitation::query()->whereKey($invitation->id)->lockForUpdate()->first();

            if (! $locked?->isPending()) {
                throw new AppException('This invitation link is no longer valid.', 409);
            }

            $user = User::create([
                'first_name' => $request->string('first_name')->toString(),
                'last_name'  => $request->string('last_name')->toString(),
                'email'      => $locked->email,
                'password'   => Hash::make($request->string('password')->toString()),
            ]);

            if ($locked->role && method_exists($user, 'assignRole')) {
                $user->assignRole($locked->role);
            }

            $locked->update(['accepted_at' => now(), 'user_id' => $user->id]);

            return $user;
        });

        return response()->json([
            'access_token' => $user->createToken('invitation')->plainTextToken,
            'token_type'   => 'Bearer',
        ], 201);
    }
}
