<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\RolesPermissions\Http\Requests\StoreRoleRequest;
use Modules\RolesPermissions\Http\Requests\UpdateRoleRequest;
use Modules\RolesPermissions\Http\Resources\RoleResource;
use Modules\RolesPermissions\Models\Role;
use Modules\RolesPermissions\Support\Guard;

/**
 * Managing roles is itself a privileged action, gated by the `roles.manage`
 * permission — which the module seeds, so the first admin can reach it.
 */
class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:roles.manage');
    }

    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->vuetifyPaginate();

        $roles->setCollection(
            $roles->getCollection()->map(fn (Role $role) => new RoleResource($role))->collect()
        );

        return response()->json($roles);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create([
            'name'       => $request->string('name')->toString(),
            'guard_name' => Guard::forUsers(),
        ]);
        $role->syncPermissions($request->input('permissions', []));

        return response()->json(['role' => new RoleResource($role->load('permissions'))], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json(['role' => new RoleResource($role->load('permissions')->loadCount('users'))]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        if ($request->has('name')) {
            $role->update(['name' => $request->string('name')->toString()]);
        }

        if ($request->has('permissions')) {
            $role->syncPermissions($request->input('permissions', []));
        }

        return response()->json(['role' => new RoleResource($role->fresh()->load('permissions'))]);
    }

    /** @throws AppException */
    public function destroy(Role $role): JsonResponse
    {
        // Deleting an assigned role silently strips access from everyone who
        // held it. Make the caller move them first.
        if ($role->users()->exists()) {
            throw new AppException('That role is still assigned to users. Reassign them before deleting it.', 409);
        }

        $role->delete();

        return response()->json()->setStatusCode(204);
    }
}
