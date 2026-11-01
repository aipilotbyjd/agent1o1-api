<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\RunStatus;
use App\Models\Auth\ApiKey;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;

/**
 * @return array{0: Workflow, 1: string, 2: Workspace}
 */
function publicRunFixture(array $nodes, array $abilities = ['workflows:read', 'workflows:write', 'runs:read']): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => $nodes, 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'Ops key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => $abilities,
    ]);

    return [$workflow->fresh(), $plainTextKey, $workspace];
}

it('lists and filters runs', function () {
    [$workflow, $key] = publicRunFixture([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
    ]);

    app(StartWorkflowRunAction::class)->execute($workflow);
    app(StartWorkflowRunAction::class)->execute($workflow);

    $index = $this->withToken($key)->getJson('/api/public/v1/runs');
    $index->assertOk();
    expect($index->json('data'))->toHaveCount(2);
    expect($index->json('meta.total'))->toBe(2);

    expect($this->withToken($key)->getJson('/api/public/v1/runs?status=failed')->json('data'))->toHaveCount(0);
});

it('exposes node runs for a run', function () {
    [$workflow, $key] = publicRunFixture([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]],
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['x' => 3]);
    $nodeRun = $run->fresh(['nodeRuns'])->nodeRuns->first();

    $this->withToken($key)->getJson("/api/public/v1/runs/{$run->id}/node-runs")
        ->assertOk()
        ->assertJsonPath('data.node_runs.0.key', 'a')
        ->assertJsonPath('data.node_runs.0.credits_used', 1);

    $this->withToken($key)->getJson("/api/public/v1/runs/{$run->id}/node-runs/{$nodeRun->id}")
        ->assertOk()
        ->assertJsonPath('data.node_run.output.x', 3)
        ->assertJsonPath('data.node_run.credits_used', 1);
});

it('cancels and retries a run over the public api', function () {
    [$workflow, $key] = publicRunFixture([
        ['key' => 'gate', 'type' => 'human_approval', 'config' => []],
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow);

    $this->withToken($key)->postJson("/api/public/v1/runs/{$run->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.run.status', 'cancelled');

    // Cancelled is terminal, so the run is now retryable.
    $retry = $this->withToken($key)->postJson("/api/public/v1/runs/{$run->id}/retry");
    $retry->assertStatus(202);
    expect($retry->json('data.run.id'))->not->toBe($run->id);
    expect($run->fresh()->status)->toBe(RunStatus::Cancelled);
});

it('returns 409 cancelling an already finished run', function () {
    [$workflow, $key] = publicRunFixture([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow);

    $this->withToken($key)->postJson("/api/public/v1/runs/{$run->id}/cancel")->assertStatus(409);
});

it('refuses run control to a read-only key', function () {
    [$workflow, $key] = publicRunFixture(
        [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        abilities: ['runs:read'],
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow);

    $this->withToken($key)->postJson("/api/public/v1/runs/{$run->id}/cancel")->assertForbidden();
    $this->withToken($key)->getJson("/api/public/v1/runs/{$run->id}")->assertOk();
});
