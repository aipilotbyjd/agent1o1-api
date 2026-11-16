<?php

use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/*
 * The viewer's permission list on the workspace payload: what the frontend
 * uses to decide which actions to offer.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

function permissionValues(Role $role): array
{
    return array_map(fn (Permission $permission) => $permission->value, $role->permissions());
}

it('lists the viewer\'s permissions on each workspace in the index', function (Role $role) {
    $user = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);

    Passport::actingAs($user);

    $this->getJson('/api/v1/workspaces')
        ->assertOk()
        ->assertJsonPath('data.workspaces.0.role', $role->value)
        ->assertJsonPath('data.workspaces.0.permissions', permissionValues($role));
})->with(Role::assignable());

it('lists the owner\'s permissions on the workspace', function () {
    Passport::actingAs($this->owner);

    $this->getJson('/api/v1/workspaces')
        ->assertOk()
        ->assertJsonPath('data.workspaces.0.permissions', permissionValues(Role::Owner));

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}")
        ->assertOk()
        ->assertJsonPath('data.workspace.permissions', permissionValues(Role::Owner));
});

it('lists the viewer\'s own permissions when showing a workspace', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    Passport::actingAs($viewer);

    $permissions = $this->getJson("/api/v1/workspaces/{$this->workspace->id}")
        ->assertOk()
        ->json('data.workspace.permissions');

    expect($permissions)
        ->toBe(permissionValues(Role::Viewer))
        ->not->toContain(Permission::WorkspaceUpdate->value);
});

it('gives a newly created workspace the owner\'s permissions', function () {
    Passport::actingAs($this->owner);

    $this->postJson('/api/v1/workspaces', ['name' => 'Globex'])
        ->assertCreated()
        ->assertJsonPath('data.workspace.permissions', permissionValues(Role::Owner));
});
