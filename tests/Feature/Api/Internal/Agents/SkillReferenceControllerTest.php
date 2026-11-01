<?php

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('lists references for a skill ordered by sort_order', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);
    $skill->references()->create(['title' => 'Second', 'content' => 'b', 'sort_order' => 2]);
    $skill->references()->create(['title' => 'First', 'content' => 'a', 'sort_order' => 1]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/references");

    $response->assertOk();
    expect($response->json('data.references.0.title'))->toBe('First');
    expect($response->json('data.references.1.title'))->toBe('Second');
});

it('creates a reference', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/references", [
        'title' => 'Refund policy',
        'content' => 'Always offer store credit first.',
    ]);

    $response->assertCreated();
    expect($skill->references()->count())->toBe(1);
});

it('updates a reference', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);
    $reference = $skill->references()->create(['title' => 'Old', 'content' => 'a']);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/references/{$reference->id}", [
        'title' => 'New',
    ])->assertOk();

    expect($reference->fresh()->title)->toBe('New');
});

it('deletes a reference', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);
    $reference = $skill->references()->create(['title' => 'Old', 'content' => 'a']);

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/references/{$reference->id}")
        ->assertNoContent();

    expect($skill->references()->count())->toBe(0);
});

it('404s updating a reference that belongs to a different skill', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);
    $otherSkill = $workspace->skills()->create(['name' => 'O', 'slug' => 'o', 'instructions' => 'v1']);
    $reference = $otherSkill->references()->create(['title' => 'Old', 'content' => 'a']);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/references/{$reference->id}", [
        'title' => 'New',
    ])->assertNotFound();
});

it('403s creating a reference without skill manage permission', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);
    $member = User::factory()->create();
    $workspace->members()->create(['user_id' => $member->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    Passport::actingAs($member);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/references", [
        'title' => 'X',
        'content' => 'y',
    ])->assertForbidden();
});

it('bumps the skill version when a reference is added, changed or removed', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/references";

    $id = $this->postJson($base, ['title' => 'A', 'content' => 'a'])->assertCreated()->json('data.reference.id');
    expect($skill->fresh()->version)->toBe(2);

    $this->patchJson("{$base}/{$id}", ['content' => 'b'])->assertOk();
    expect($skill->fresh()->version)->toBe(3);

    $this->patchJson("{$base}/{$id}", ['content' => 'b'])->assertOk();
    expect($skill->fresh()->version)->toBe(3);

    $this->deleteJson("{$base}/{$id}")->assertNoContent();
    expect($skill->fresh()->version)->toBe(4);
});

it('rejects null sort_order and overlong content', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}/references";

    $this->postJson($base, ['title' => 'A', 'content' => 'a', 'sort_order' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('sort_order');
    $this->postJson($base, ['title' => 'A', 'content' => str_repeat('a', 100001)])
        ->assertUnprocessable()->assertJsonValidationErrors('content');
});
