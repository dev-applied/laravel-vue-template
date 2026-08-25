<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\RolesPermissions\Http\Resources\PermissionResource;
use Modules\RolesPermissions\Models\Permission;

/**
 * Read-only. Permissions are defined by the application (a seeder or a
 * migration), not created through the UI — an app cannot check a permission
 * nobody wrote code for, so inventing them at runtime only produces dead rows.
 */
class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:roles.manage');
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'permissions' => PermissionResource::collection(
                Permission::query()->orderBy('name')->get()
            ),
        ]);
    }
}
