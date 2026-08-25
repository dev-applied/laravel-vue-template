<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Modules\Users\Http\Requests\StoreUserRequest;
use Modules\Users\Http\Requests\UpdateUserRequest;
use Modules\Users\Http\Resources\UserResource;
use Modules\Users\Support\UserGuard;

class UserController extends Controller
{
    public function __construct(private readonly UserGuard $guard) {}

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                // GROUPED. The kernel's version was
                //   ->where(...)->orWhere(...)
                // ungrouped, so the moment any other constraint joins the
                // query the OR escapes it and the filter leaks every row it
                // was meant to exclude.
                $query->where(function (Builder $q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request) {
                match ($request->string('status')->toString()) {
                    'active'      => $query->whereNull('deactivated_at'),
                    'deactivated' => $query->whereNotNull('deactivated_at'),
                    default       => null,
                };
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->vuetifyPaginate();

        // through() is the paginator's own map — it keeps the pagination
        // envelope and wraps each row in the resource.
        return response()->json($users->through(fn (User $u) => new UserResource($u)));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $sendsInvite = empty($data['password']);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            // An unusable random password rather than null: a null password
            // column makes every Hash::check downstream a potential fatal, and
            // some auth paths treat an empty hash as a match.
            'password' => Hash::make($sendsInvite ? Str::random(64) : $data['password']),
        ]);

        if ($sendsInvite) {
            // Laravel's own reset flow — no assumption that the Invitations
            // module is installed.
            Password::sendResetLink(['email' => $user->email]);
        }

        return response()->json([
            'user'          => new UserResource($user),
            'invitedByMail' => $sendsInvite,
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        return response()->json(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->update($data);

        // Assigned directly rather than through update(): email_verified_at and
        // deactivated_at are not in the KERNEL's User::$fillable, and a
        // copy-in module must not require that file to be edited before it
        // works. A mass-assignment would be silently dropped.
        if ($emailChanged) {
            // Changing an email invalidates the verification — the new address
            // has not proved anything.
            $user->email_verified_at = null;
            $user->save();
        }

        return response()->json(new UserResource($user->fresh()));
    }

    /**
     * Deactivate — the default way to remove someone.
     *
     * Deleting a user cascades across every module that references them, and
     * "we need to see what they did" arrives about a week later. Deactivation
     * answers that; deletion does not.
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        $this->guard->assertNotSelf($request->user(), $user, 'deactivate');

        // One transaction around the check AND the write. The guard's row lock
        // does nothing outside one — it would be released before the write.
        $this->guard->protecting($user, function () use ($user) {
            // Direct assignment — see the note in update(); deactivated_at is
            // not in the kernel's $fillable.
            $user->deactivated_at = now();
            $user->save();

            // Signing them out everywhere is the point of deactivating. Without
            // this an existing token keeps working and "deactivated" means
            // nothing until it expires. Inside the transaction so a failure
            // here cannot leave an account that reads as deactivated while its
            // tokens keep working — which is precisely the state the method
            // exists to prevent.
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
        });

        return response()->json(new UserResource($user->fresh()));
    }

    public function reactivate(User $user): JsonResponse
    {
        $user->deactivated_at = null;
        $user->save();

        return response()->json(new UserResource($user->fresh()));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->guard->assertNotSelf($request->user(), $user, 'delete');

        // Same transaction requirement as deactivate(), and the stakes are
        // higher here: the row is gone for good.
        $this->guard->protecting($user, fn () => $user->delete());

        return response()->json(['message' => 'User deleted.']);
    }
}
