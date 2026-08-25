<?php

declare(strict_types=1);

use Modules\RolesPermissions\Database\Seeders\RolesPermissionsSeeder;
use Modules\RolesPermissions\Models\Permission;
use Modules\RolesPermissions\Models\Role;
use Modules\RolesPermissions\Support\Guard;
use Modules\RolesPermissions\Tests\Support\AccessControlledUser;

beforeEach(function () {
    // Point the auth provider at the trait-bearing model. This is what a real
    // install looks like (the trait goes on whatever App\Models\User is), and
    // it is what spatie reads to infer guards and to resolve Role::users().
    config(['auth.providers.users.model' => AccessControlledUser::class]);

    (new RolesPermissionsSeeder)->run();

    $this->admin = AccessControlledUser::factory()->create();
    $this->admin->assignRole('admin');

    $this->plain = AccessControlledUser::factory()->create();
});

test('the seeder bootstraps the permission its own screens need', function () {
    expect(Permission::query()->where('name', 'roles.manage')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'admin')->first()->hasPermissionTo('roles.manage'))->toBeTrue();
});

test('role management is gated by roles.manage', function () {
    $this->actingAs($this->plain)->getJson('/api/v1/roles')->assertForbidden();
    $this->actingAs($this->admin)->getJson('/api/v1/roles')->assertOk();
});

test('a role can be created with permissions attached', function () {
    Permission::findOrCreate('items.edit', Guard::forUsers());

    $this->actingAs($this->admin)
        ->postJson('/api/v1/roles', ['name' => 'editor', 'permissions' => ['items.edit']])
        ->assertCreated()
        ->assertJsonPath('role.name', 'editor')
        ->assertJsonPath('role.permissions.0.name', 'items.edit');

    expect(Role::findByName('editor', Guard::forUsers())->hasPermissionTo('items.edit'))->toBeTrue();
});

test('creating a role rejects an unknown permission', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/roles', ['name' => 'editor', 'permissions' => ['does.not.exist']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions.0']);
});

test('role names must be unique', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/roles', ['name' => 'admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('updating a role syncs its permissions rather than appending', function () {
    Permission::findOrCreate('items.edit', Guard::forUsers());
    Permission::findOrCreate('items.delete', Guard::forUsers());
    $role = Role::findOrCreate('editor', Guard::forUsers());
    $role->syncPermissions(['items.edit', 'items.delete']);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/roles/{$role->id}", ['permissions' => ['items.edit']])
        ->assertOk();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['items.edit']);
});

test('a role still assigned to users cannot be deleted', function () {
    $role = Role::findByName('admin', Guard::forUsers());

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/roles/{$role->id}")
        ->assertStatus(409);

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

test('an unassigned role can be deleted', function () {
    $role = Role::findOrCreate('temporary', Guard::forUsers());

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/roles/{$role->id}")
        ->assertNoContent();

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
});

test('the permission list groups by dotted prefix for the matrix ui', function () {
    Permission::findOrCreate('items.edit', Guard::forUsers());

    $this->actingAs($this->admin)
        ->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonPath('permissions.0.group', 'items');
});

test('the user payload exposes the exact shape the frontend reads', function () {
    // $auth.hasPermission reads user.all_permissions[].name, and
    // Authorization.ts reads user.role. Both must be APPENDED, since
    // AuthUserResource serialises with toArray().
    Permission::findOrCreate('items.edit', Guard::forUsers());
    $this->admin->givePermissionTo('items.edit');

    $payload = $this->admin->fresh()->toArray();

    expect($payload)->toHaveKey('all_permissions')
        ->and($payload)->toHaveKey('role')
        ->and($payload['role'])->toBe('admin')
        ->and(collect($payload['all_permissions'])->pluck('name'))
        ->toContain('items.edit')
        ->toContain('roles.manage');
});

test('permissions inherited from a role appear in the payload', function () {
    expect(collect($this->admin->fresh()->toArray()['all_permissions'])->pluck('name'))
        ->toContain('roles.manage');
});

test('a user with no roles exposes a null role and no permissions', function () {
    $payload = $this->plain->fresh()->toArray();

    expect($payload['role'])->toBeNull()
        ->and($payload['all_permissions'])->toBe([]);
});

test('the permission middleware alias is registered', function () {
    // Route definitions use ->middleware('permission:x'); an unregistered alias
    // would throw rather than deny.
    expect(app('router')->getMiddleware())->toHaveKeys(['role', 'permission', 'role_or_permission']);
});

test('grant-admin seeds and assigns on a fresh install', function () {
    Role::query()->where('name', 'admin')->delete();
    $user = AccessControlledUser::factory()->create(['email' => 'boot@example.com']);

    $this->artisan('roles:grant-admin', ['email' => 'boot@example.com'])->assertSuccessful();

    expect($user->fresh()->hasRole('admin'))->toBeTrue();
});

test('grant-admin fails clearly for an unknown email', function () {
    $this->artisan('roles:grant-admin', ['email' => 'nobody@example.com'])->assertFailed();
});

test('role routes require authentication', function () {
    $this->getJson('/api/v1/roles')->assertUnauthorized();
    $this->getJson('/api/v1/permissions')->assertUnauthorized();
});
