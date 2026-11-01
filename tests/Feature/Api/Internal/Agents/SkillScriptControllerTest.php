<?php

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('lists scripts for a skill', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);
    $skill->scripts()->create(['name' => 'Format', 'language' => 'python', 'code' => 'print(1)']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/scripts");

    $response->assertOk();
    expect($response->json('data.scripts'))->toHaveCount(1);
});

it('creates a script', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/scripts", [
        'name' => 'Format Number',
        'language' => 'python',
        'code' => "print('hi')",
    ]);

    $response->assertCreated();
    expect($response->json('data.script.is_enabled'))->toBeTrue();
});

it('rejects an unsupported language', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/scripts", [
        'name' => 'Bad',
        'language' => 'ruby',
        'code' => 'puts 1',
    ])->assertUnprocessable();
});

it('toggles is_enabled on update', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);
    $script = $skill->scripts()->create(['name' => 'Format', 'language' => 'python', 'code' => 'print(1)']);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/scripts/{$script->id}", [
        'is_enabled' => false,
    ])->assertOk();

    expect($script->fresh()->is_enabled)->toBeFalse();
});

it('deletes a script', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);
    $script = $skill->scripts()->create(['name' => 'Format', 'language' => 'python', 'code' => 'print(1)']);

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/scripts/{$script->id}")
        ->assertNoContent();

    expect($skill->scripts()->count())->toBe(0);
});

it('404s deleting a script that belongs to a different skill', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);
    $otherSkill = $workspace->skills()->create(['name' => 'O', 'slug' => 'o', 'instructions' => 'v1']);
    $script = $otherSkill->scripts()->create(['name' => 'Format', 'language' => 'python', 'code' => 'print(1)']);

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/scripts/{$script->id}")
        ->assertNotFound();
});

it('rejects a null is_enabled', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/scripts", [
        'name' => 'X', 'language' => 'python', 'code' => '1', 'is_enabled' => null,
    ])->assertUnprocessable()->assertJsonValidationErrors('is_enabled');
});

it('keeps script source from a viewer', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/scripts")->assertForbidden();
});
