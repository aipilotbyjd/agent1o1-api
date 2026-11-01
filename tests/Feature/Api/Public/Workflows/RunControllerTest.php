<?php

use App\Models\Auth\ApiKey;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

it('starts and reads a run through the public api with a valid ability', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'CI key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['workflows:read', 'workflows:write', 'runs:read'],
    ]);

    $response = $this->withToken($plainTextKey)
        ->postJson("/api/public/v1/workflows/{$workflow->id}/runs");

    $response->assertStatus(202);
    $runId = $response->json('data.run.id');

    $this->withToken($plainTextKey)
        ->getJson("/api/public/v1/runs/{$runId}")
        ->assertOk()
        ->assertJsonPath('data.run.status', 'completed')
        ->assertJsonPath('data.run.total_credits_used', 1);
});

it('rejects starting a run through the public api without the workflows:write ability', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'Read-only key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['workflows:read'],
    ]);

    $this->withToken($plainTextKey)
        ->postJson("/api/public/v1/workflows/{$workflow->id}/runs")
        ->assertForbidden();
});

it('404s reading a run belonging to a different workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    [$otherWorkspace] = [app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other'])];

    $foreignWorkflow = Workflow::factory()->forWorkspace($otherWorkspace)->create();
    $foreignWorkflow->replaceGraph(['nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []]);
    $foreignWorkflow->publishVersion(publisher: $owner);
    $foreignWorkflow = $foreignWorkflow->fresh();

    $otherKey = ApiKey::generatePlainTextKey();
    $otherWorkspace->apiKeys()->create([
        'name' => 'Other key',
        'hashed_key' => ApiKey::hash($otherKey),
        'abilities' => ['*'],
    ]);
    $foreignRun = $this->withToken($otherKey)
        ->postJson("/api/public/v1/workflows/{$foreignWorkflow->id}/runs")
        ->json('data.run.id');

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'CI key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['*'],
    ]);

    $this->withToken($plainTextKey)
        ->getJson("/api/public/v1/runs/{$foreignRun}")
        ->assertNotFound();
});
