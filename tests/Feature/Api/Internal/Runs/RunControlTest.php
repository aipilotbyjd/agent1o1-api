<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workflow, 1: User, 2: Workspace}
 */
function runControlWorkflow(array $nodes, array $edges = []): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => $nodes, 'edges' => $edges]);
    $workflow->publishVersion(publisher: $owner);

    return [$workflow->fresh(), $owner, $workspace];
}

it('cancels an in-flight run over the api', function () {
    [$workflow, $owner, $workspace] = runControlWorkflow([
        ['key' => 'gate', 'type' => 'human_approval', 'config' => []],
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.run.status', 'cancelled');

    expect($run->fresh()->status)->toBe(RunStatus::Cancelled);
});

it('returns 409 when cancelling a run that already finished', function () {
    [$workflow, $owner, $workspace] = runControlWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/cancel")
        ->assertStatus(409)
        ->assertJsonPath('success', false);
});

it('retries a finished run as a new run pinned to the same version', function () {
    [$workflow, $owner, $workspace] = runControlWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]],
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['x' => 7]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/retry");

    $response->assertStatus(202);
    expect($response->json('data.run.retried_from_run_id'))->toBe($run->id);
    expect($response->json('data.run.status'))->toBe('completed');
    expect($response->json('data.run.workflow_version_id'))->toBe($run->workflow_version_id);
    expect($response->json('data.run.output'))->toBe(['a' => ['x' => 7]]);
    expect(Run::count())->toBe(2);
});

it('carries over upstream node runs when retrying from a node', function () {
    [$workflow, $owner, $workspace] = runControlWorkflow(
        [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => ['y' => 'nodes.a.x']]],
        ],
        [['from' => 'a', 'to' => 'b']],
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['x' => 5]);
    $originalA = $run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'a');

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/retry", [
        'from_node_key' => 'b',
    ]);

    $response->assertStatus(202);

    $retry = Run::find($response->json('data.run.id'))->fresh(['nodeRuns']);
    $carriedA = $retry->nodeRuns->firstWhere('key', 'a');

    expect($retry->status)->toBe(RunStatus::Completed);
    expect($carriedA->status)->toBe(NodeRunStatus::Completed);
    // Copied, not re-executed: the timings are the original run's.
    expect($carriedA->started_at->toIso8601String())->toBe($originalA->started_at->toIso8601String());
    expect($retry->nodeRuns->firstWhere('key', 'b')->output)->toBe(['y' => 5]);
});

it('returns 409 when retrying from a node key the version does not have', function () {
    [$workflow, $owner, $workspace] = runControlWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/retry", ['from_node_key' => 'nope'])
        ->assertStatus(409);
});

it('returns 409 when retrying a run that is still in flight', function () {
    [$workflow, $owner, $workspace] = runControlWorkflow([
        ['key' => 'gate', 'type' => 'human_approval', 'config' => []],
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/retry")
        ->assertStatus(409);
});

it('lists and shows node runs in detail', function () {
    [$workflow, $owner, $workspace] = runControlWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]],
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['x' => 1]);
    $nodeRun = $run->fresh(['nodeRuns'])->nodeRuns->first();

    Passport::actingAs($owner);

    $index = $this->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/node-runs");
    $index->assertOk();
    expect($index->json('data.node_runs'))->toHaveCount(1);

    expect($index->json('data.node_runs.0.credits_used'))->toBe(1);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/node-runs/{$nodeRun->id}")
        ->assertOk()
        ->assertJsonPath('data.node_run.key', 'a')
        ->assertJsonPath('data.node_run.credits_used', 1)
        ->assertJsonPath('data.node_run.output.x', 1)
        ->assertJsonPath('data.node_run.input.x', 1);
});

it('404s on a node run belonging to a different run', function () {
    [$workflow, $owner, $workspace] = runControlWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
    ]);

    $first = app(StartWorkflowRunAction::class)->execute($workflow);
    $second = app(StartWorkflowRunAction::class)->execute($workflow);
    $otherNodeRun = $second->fresh(['nodeRuns'])->nodeRuns->first();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$first->id}/node-runs/{$otherNodeRun->id}")
        ->assertNotFound();
});
