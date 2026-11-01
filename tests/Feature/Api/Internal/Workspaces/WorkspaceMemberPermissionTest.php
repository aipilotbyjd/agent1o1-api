<?php

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

function loginTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

it('lets a viewer read the workspace but not update it', function () {
    $owner = User::factory()->create(['password' => 'Password1!']);
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $viewer = User::factory()->create(['password' => 'Password1!']);
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    $tokens = loginTokens($viewer->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->getJson("/api/v1/workspaces/{$workspace->id}")
        ->assertOk();

    $this->withToken($tokens['access_token'])
        ->patchJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed'])
        ->assertForbidden();
});

it('denies a non-member access to a workspace entirely', function () {
    $owner = User::factory()->create(['password' => 'Password1!']);
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $stranger = User::factory()->create(['password' => 'Password1!']);
    $tokens = loginTokens($stranger->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->getJson("/api/v1/workspaces/{$workspace->id}")
        ->assertForbidden();
});

it('lets the owner delete the workspace', function () {
    $owner = User::factory()->create(['password' => 'Password1!']);
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $tokens = loginTokens($owner->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->deleteJson("/api/v1/workspaces/{$workspace->id}")
        ->assertNoContent();
});
