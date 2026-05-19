<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

/**
 * Canonical per-record authorization for the Item resource.
 *
 * Resolved automatically by Laravel via the App\Policies\<Model>Policy
 * naming convention (Gate::policy() is implicit). Wired into the controller
 * with `$this->authorizeResource(Item::class, 'item')` so every action
 * checks the matching policy method before running.
 *
 * Baseline: any authenticated user can list, view, and create items; only
 * the owner can update or delete. Adjust per-resource for your real
 * authorization model (teams, roles, sharing, etc.).
 */
class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Item $item): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Item $item): bool
    {
        return $item->owner_id === $user->id;
    }

    public function delete(User $user, Item $item): bool
    {
        return $item->owner_id === $user->id;
    }
}
