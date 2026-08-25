<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only user typeahead.
 *
 * This is deliberately NOT user management. Any signed-in user needs to be able
 * to answer "who can I assign this to" — that is what every AppAutoComplete
 * pointed at `users` is asking. Management (create / edit / deactivate / delete)
 * lives in the Users module behind `can:manage-users`, at its own path, so a
 * regular user picking an owner never trips an authorization gate.
 */
class UserLookupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = mb_trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                // Grouped so the OR cannot escape past any other constraint
                // added below (the deactivation filter, most importantly).
                $query->where(function (Builder $q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            // Only filter when the Users module has been installed and has run
            // its migration — the kernel `users` table has no such column.
            ->when(
                $request->boolean('include_inactive') === false
                    && \Illuminate\Support\Facades\Schema::hasColumn('users', 'deactivated_at'),
                fn (Builder $query) => $query->whereNull('deactivated_at')
            )
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(50)
            ->get(['id', 'first_name', 'last_name', 'email']);

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'id'        => $user->id,
                'full_name' => $user->full_name,
                'email'     => $user->email,
            ])->all(),
        ]);
    }
}
