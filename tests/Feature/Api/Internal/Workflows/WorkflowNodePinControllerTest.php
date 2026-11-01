<?php

use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

function workflowWithOneNode(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);

    return [$workflow->fresh(), $owner];
}

it('pins manual JSON data onto a node', function () {
    [$workflow, $owner] = workflowWithOneNode();
    $node = $workflow->nodes()->first();

    Passport::actingAs($owner);

    $response = $this->postJson(
        "/api/v1/workspaces/{$workflow->workspace_id}/workflows/{$workflow->id}/nodes/{$node->id}/pin",
        ['data' => ['greeting' => 'hi']],
    );

    $response->assertOk();
    expect($response->json('data.node.pinned_data'))->toBe(['greeting' => 'hi']);
    expect($workflow->fresh()->has_unpublished_changes)->toBeTrue();
});

it('pins data copied from a prior node run output', function () {
    [$workflow, $owner] = workflowWithOneNode();
    $node = $workflow->nodes()->first();

    $run = Run::factory()->forWorkflow($workflow)->create();
    $nodeRun = NodeRun::factory()->forRun($run)->create(['key' => 'a', 'type' => 'transform', 'output' => ['from' => 'run']]);

    Passport::actingAs($owner);

    $response = $this->postJson(
        "/api/v1/workspaces/{$workflow->workspace_id}/workflows/{$workflow->id}/nodes/{$node->id}/pin",
        ['node_run_id' => $nodeRun->id],
    );

    $response->assertOk();
    expect($response->json('data.node.pinned_data'))->toBe(['from' => 'run']);
});

it('unpins a node and survives a graph save when the type is unchanged', function () {
    [$workflow, $owner] = workflowWithOneNode();
    $node = $workflow->nodes()->first();
    $node->forceFill(['pinned_data' => ['x' => 1], 'pinned_at' => now(), 'pinned_by' => $owner->id])->save();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['y' => 'input.z']]]],
        'edges' => [],
    ]);

    expect($workflow->nodes()->first()->pinned_data)->toBe(['x' => 1]);

    Passport::actingAs($owner);

    $freshNode = $workflow->nodes()->first();
    $this->deleteJson("/api/v1/workspaces/{$workflow->workspace_id}/workflows/{$workflow->id}/nodes/{$freshNode->id}/pin")
        ->assertOk();

    expect($workflow->nodes()->first()->pinned_data)->toBeNull();
});

it('drops pinned data across a graph save when the node type changes', function () {
    [$workflow] = workflowWithOneNode();
    $node = $workflow->nodes()->first();
    $node->forceFill(['pinned_data' => ['x' => 1], 'pinned_at' => now()])->save();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'run_code', 'config' => ['operations' => []]]],
        'edges' => [],
    ]);

    expect($workflow->nodes()->first()->pinned_data)->toBeNull();
});
