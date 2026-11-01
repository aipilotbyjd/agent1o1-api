<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Tools\NodeTool;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Nodes\CustomNode;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\DataTransform\CallApiNode;
use App\Services\Agents\ToolRegistry;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * A `CustomNode` can be authored today but nothing can execute one yet, so
 * `NodeRegistry::has()` answers false for a `custom:{id}` type. These cover
 * the call sites that used to ask `has()` and then `resolve()`, and took the
 * whole request down when the answer disagreed.
 */
function workspaceWithCustomNode(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $custom = CustomNode::factory()->create(['workspace_id' => $workspace->id]);

    return [$owner, $workspace, $custom];
}

it('reports a custom node type as unresolvable while execution is unimplemented', function () {
    [, , $custom] = workspaceWithCustomNode();

    expect(app(NodeRegistry::class)->has("custom:{$custom->id}"))->toBeFalse();
});

it('saves and publishes a graph containing a custom node instead of erroring', function () {
    [$owner, $workspace, $custom] = workspaceWithCustomNode();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => "custom:{$custom->id}", 'config' => []]],
        'edges' => [],
    ]);

    $version = $workflow->publishVersion(publisher: $owner);

    expect($version->version)->toBe(1);
    expect($workflow->fresh()->current_version_id)->toBe($version->id);
});

it('fails only the custom node at run time, leaving the rest of the graph to route around it', function () {
    [$owner, $workspace, $custom] = workspaceWithCustomNode();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => "custom:{$custom->id}", 'config' => []],
            ['key' => 'recovery', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'recovery', 'condition' => 'error']],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh())->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);

    $failed = $run->nodeRuns->firstWhere('key', 'a');
    expect($failed->status)->toBe(NodeRunStatus::Failed);
    expect($failed->error)->toContain("Custom nodes can't be executed yet");

    expect($run->nodeRuns->firstWhere('key', 'recovery')->status)->toBe(NodeRunStatus::Completed);
});

it('drops a custom node tool binding rather than erroring the agent turn', function () {
    [, $workspace, $custom] = workspaceWithCustomNode();

    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $agent->toolBindings()->create(['node_type' => "custom:{$custom->id}", 'config' => [], 'exposed_fields' => []]);
    $agent->toolBindings()->create(['node_type' => 'call_api', 'config' => [], 'exposed_fields' => []]);

    $tools = app(ToolRegistry::class)->toolsFor($agent, Run::factory()->create());

    // The built-in binding survives; the custom one is filtered out, so the
    // turn still gets a working tool list instead of throwing.
    $nodeTools = array_values(array_filter($tools, fn ($tool) => $tool instanceof NodeTool));

    expect($nodeTools)->toHaveCount(1);
    expect($nodeTools[0]->name())->toBe((new CallApiNode)->type());
});

it('refuses to bind a custom node as an agent tool', function () {
    [$owner, $workspace, $custom] = workspaceWithCustomNode();

    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tool-bindings", [
        'node_type' => "custom:{$custom->id}",
    ])->assertStatus(422);
});
