<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('records version 1 when an agent is created', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'Be brief.']);

    expect($agent->versions()->count())->toBe(1);
    expect($agent->versions()->sole()->snapshot['instructions'])->toBe('Be brief.');
});

it('records a new version when behavior changes but not for cosmetic edits', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'Be brief.']);

    $agent->update(['description' => 'A friendlier description']);
    expect($agent->versions()->count())->toBe(1);

    $agent->update(['instructions' => 'Be thorough.']);
    expect($agent->versions()->count())->toBe(2);
    expect($agent->versions()->orderByDesc('version')->first()->snapshot['instructions'])->toBe('Be thorough.');
});

it('lists and shows versions by version number', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'v1']);
    $agent->update(['instructions' => 'v2']);

    Passport::actingAs($owner);

    $index = $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/versions");
    $index->assertOk();
    expect(array_column($index->json('data.versions'), 'version'))->toBe([2, 1]);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/versions/1")
        ->assertOk()
        ->assertJsonPath('data.version.snapshot.instructions', 'v1');

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/versions/99")
        ->assertNotFound();
});

it('restores an earlier version as a new version rather than rewinding history', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'original']);
    $agent->update(['instructions' => 'regrettable change']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/versions/1/restore")
        ->assertOk()
        ->assertJsonPath('data.agent.instructions', 'original')
        ->assertJsonPath('data.version.version', 3);

    expect($agent->fresh()->instructions)->toBe('original');
    // Nothing was deleted: v2 is still readable.
    expect($agent->versions()->count())->toBe(3);
});

it('duplicates an agent with its tools and skills but not its sessions', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['name' => 'Support']);
    $agent->toolBindings()->create(['node_type' => 'transform', 'config' => ['mapping' => []], 'exposed_fields' => null]);
    $agent->sessions()->create(['workspace_id' => $workspace->id]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/duplicate");
    $response->assertCreated();

    $copy = Agent::find($response->json('data.agent.id'));
    expect($copy->name)->toBe('Support (copy)');
    expect($copy->slug)->not->toBe($agent->slug);
    expect($copy->toolBindings)->toHaveCount(1);
    expect($copy->sessions)->toHaveCount(0);
    expect($copy->versions()->count())->toBe(1);
});

it('applies a pinned snapshot to a copy, leaving the live agent unchanged', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'be terse']);

    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    $agent->forceFill(['instructions' => 'be verbose'])->save();

    $session = $session->fresh();
    expect($session->pinnedAgent()->instructions)->toBe('be terse');

    // The session's own `agent` relation must not be left holding the
    // snapshot as a dirty attribute: a later save() anywhere in the request
    // would otherwise persist a stale snapshot over the live agent.
    expect($session->agent->isDirty())->toBeFalse();

    $session->agent->save();

    expect($agent->fresh()->instructions)->toBe('be verbose');
});
