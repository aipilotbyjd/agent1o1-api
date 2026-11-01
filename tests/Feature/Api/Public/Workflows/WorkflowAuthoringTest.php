<?php

use App\Models\Auth\ApiKey;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;

/**
 * @return array{0: Workspace, 1: string, 2: User}
 */
function publicApiWorkspace(array $abilities): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'Integration key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => $abilities,
    ]);

    return [$workspace, $plainTextKey, $owner];
}

it('creates, edits, publishes and runs a workflow entirely over the public api', function () {
    [$workspace, $key] = publicApiWorkspace(['workflows:read', 'workflows:write', 'runs:read']);

    $created = $this->withToken($key)->postJson('/api/public/v1/workflows', ['name' => 'Nightly sync']);
    $created->assertCreated();
    $workflowId = $created->json('data.workflow.id');

    $this->withToken($key)->putJson("/api/public/v1/workflows/{$workflowId}/graph", [
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]]],
        'edges' => [],
    ])->assertOk();

    $this->withToken($key)->getJson("/api/public/v1/workflows/{$workflowId}/graph")
        ->assertOk()
        ->assertJsonPath('data.graph.nodes.0.key', 'a')
        ->assertJsonPath('data.has_unpublished_changes', true);

    $this->withToken($key)->postJson("/api/public/v1/workflows/{$workflowId}/versions", ['notes' => 'first cut'])
        ->assertCreated()
        ->assertJsonPath('data.version.version', 1);

    $run = $this->withToken($key)->postJson("/api/public/v1/workflows/{$workflowId}/runs", [
        'input' => ['x' => 'hello'],
    ]);

    $run->assertStatus(202);
    expect($run->json('data.run.status'))->toBe('completed');
});

it('reports validation issues instead of publishing a broken graph', function () {
    [$workspace, $key] = publicApiWorkspace(['workflows:write']);

    $workflowId = $this->withToken($key)
        ->postJson('/api/public/v1/workflows', ['name' => 'Broken'])
        ->json('data.workflow.id');

    $this->withToken($key)->putJson("/api/public/v1/workflows/{$workflowId}/graph", [
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b'], ['from' => 'b', 'to' => 'a']],
    ])->assertOk();

    $validation = $this->withToken($key)->postJson("/api/public/v1/workflows/{$workflowId}/graph/validate");
    $validation->assertOk()->assertJsonPath('data.valid', false);

    $this->withToken($key)->postJson("/api/public/v1/workflows/{$workflowId}/versions")->assertStatus(422);
});

it('refuses workflow writes to a read-only key', function () {
    [$workspace, $key] = publicApiWorkspace(['workflows:read']);

    $this->withToken($key)->postJson('/api/public/v1/workflows', ['name' => 'Nope'])->assertForbidden();
    $this->withToken($key)->getJson('/api/public/v1/workflows')->assertOk();
});

it('excludes internal (loop-mode) workflows from the public api listing', function () {
    [$workspace, $key] = publicApiWorkspace(['workflows:read']);
    Workflow::factory()->forWorkspace($workspace)->create();
    Workflow::factory()->forWorkspace($workspace)->create(['is_internal' => true]);

    $response = $this->withToken($key)->getJson('/api/public/v1/workflows')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('never exposes another workspaces workflow', function () {
    [$mine, $key] = publicApiWorkspace(['workflows:read', 'workflows:write']);
    [$theirs] = publicApiWorkspace(['workflows:read']);

    $foreign = Workflow::factory()->forWorkspace($theirs)->create();

    $this->withToken($key)->getJson("/api/public/v1/workflows/{$foreign->id}")->assertNotFound();
    $this->withToken($key)->patchJson("/api/public/v1/workflows/{$foreign->id}", ['name' => 'Hijacked'])->assertNotFound();
});

it('exposes a workflows input interface to integrators', function () {
    [$workspace, $key, $owner] = publicApiWorkspace(['workflows:read', 'workflows:write']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create([
        'input_schema' => ['fields' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'string', 'required' => true],
        ]],
    ]);
    $workflow->replaceGraph(['nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);

    $this->withToken($key)->getJson("/api/public/v1/workflows/{$workflow->id}/interface")
        ->assertOk()
        ->assertJsonPath('data.interface.source', 'declared')
        ->assertJsonPath('data.interface.fields.0.key', 'email')
        ->assertJsonPath('data.interface.published', true);
});

it('authenticates a scoped public request once and does not rewrite a fresh last-used time', function () {
    [$workspace, $key] = publicApiWorkspace(['workflows:read']);

    $apiKey = $workspace->apiKeys()->sole();
    $recent = now()->subMinute()->startOfSecond();
    $apiKey->forceFill(['last_used_at' => $recent])->save();

    $this->withToken($key)->getJson('/api/public/v1/workflows')->assertOk();

    expect($apiKey->fresh()->last_used_at->equalTo($recent))->toBeTrue();
});

it('stamps last-used on a key that has never been used', function () {
    [$workspace, $key] = publicApiWorkspace(['workflows:read']);

    $this->withToken($key)->getJson('/api/public/v1/workflows')->assertOk();

    expect($workspace->apiKeys()->sole()->last_used_at)->not->toBeNull();
});
