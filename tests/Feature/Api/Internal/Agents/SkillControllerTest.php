<?php

use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('creates a skill with a generated slug', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/skills", [
        'name' => 'Refund Policy',
        'instructions' => 'Offer store credit first.',
    ]);

    $response->assertCreated();
    expect($response->json('data.skill.slug'))->toStartWith('refund-policy-');
    expect($response->json('data.skill.version'))->toBe(1);
});

it('creates a skill with its references and scripts in one request', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/skills", [
        'name' => 'Weekly MRR Report',
        'instructions' => 'Fill the "Report template".',
        'references' => [
            ['title' => 'Report template', 'content' => '## MRR'],
            ['title' => 'Glossary', 'content' => 'MRR: monthly recurring revenue'],
        ],
        'scripts' => [['name' => 'compute_mrr', 'description' => null, 'language' => 'python', 'code' => 'print(1)']],
    ]);

    $response->assertCreated();
    $skill = $workspace->skills()->sole();
    expect($skill->references->pluck('title', 'sort_order')->all())->toBe([0 => 'Report template', 1 => 'Glossary']);
    expect($skill->scripts()->sole()->only(['name', 'language', 'is_enabled']))
        ->toBe(['name' => 'compute_mrr', 'language' => 'python', 'is_enabled' => true]);
});

it('saves nothing when one of the scripts is invalid', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/skills", [
        'name' => 'S',
        'instructions' => 'I',
        'scripts' => [['name' => 'x', 'language' => 'ruby', 'code' => 'puts 1']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['scripts.0.language']);

    $this->assertDatabaseCount('skills', 0);
});

it('bumps the version when instructions change but not for cosmetic edits', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}", ['color' => '#fff'])
        ->assertOk();
    expect($skill->fresh()->version)->toBe(1);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}", ['instructions' => 'v2'])
        ->assertOk();
    expect($skill->fresh()->version)->toBe(2);
});

it('attaches and detaches a skill to an agent', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/skills/{$skill->id}")
        ->assertOk();
    expect($agent->skills()->count())->toBe(1);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/skills/{$skill->id}")
        ->assertNoContent();
    expect($agent->skills()->count())->toBe(0);
});

it('404s attaching a skill from a different workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $foreignSkill = $otherWorkspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/skills/{$foreignSkill->id}")
        ->assertNotFound();
});

it('does not bump the version when the instructions are re-sent unchanged', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}", ['instructions' => 'v1', 'color' => '#fff'])
        ->assertOk();

    expect($skill->fresh()->version)->toBe(1);
});

it('refuses a duplicate slug or name instead of erroring', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->skills()->create(['name' => 'Refunds', 'slug' => 'refunds', 'instructions' => 'v1']);
    $other = $workspace->skills()->create(['name' => 'Other', 'slug' => 'other', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/skills", ['name' => 'New', 'slug' => 'refunds', 'instructions' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('slug');
    $this->postJson("/api/v1/workspaces/{$workspace->id}/skills", ['name' => 'REFUNDS', 'instructions' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/skills/{$other->id}", ['slug' => 'refunds'])
        ->assertUnprocessable()->assertJsonValidationErrors('slug');
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/skills/{$other->id}", ['name' => 'Other', 'slug' => 'other'])
        ->assertOk();
});

it('rejects a null is_shared, nested tags and an overlong description', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/skills", ['name' => 'A', 'instructions' => 'x', 'is_shared' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('is_shared');
    $this->postJson("/api/v1/workspaces/{$workspace->id}/skills", ['name' => 'A', 'instructions' => 'x', 'tags' => [['nested']]])
        ->assertUnprocessable()->assertJsonValidationErrors('tags.0');
    $this->postJson("/api/v1/workspaces/{$workspace->id}/skills", ['name' => 'A', 'instructions' => 'x', 'description' => str_repeat('a', 501)])
        ->assertUnprocessable()->assertJsonValidationErrors('description');
});

it('shows, lists and deletes skills, and forbids a viewer from managing them', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $skill = $workspace->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    Passport::actingAs($viewer);
    $this->getJson("/api/v1/workspaces/{$workspace->id}/skills")->assertOk();
    $this->getJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}")->assertOk();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}")->assertForbidden();

    Passport::actingAs($owner);
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}")->assertNoContent();
    $this->assertSoftDeleted($skill);
});

it('does not reach into another workspace\'s skill', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $foreign = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $skill = $foreign->skills()->create(['name' => 'S', 'slug' => 's', 'instructions' => 'v1']);

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}")->assertNotFound();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/skills/{$skill->id}")->assertNotFound();
});
