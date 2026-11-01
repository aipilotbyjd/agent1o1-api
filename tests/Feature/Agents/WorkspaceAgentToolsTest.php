<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Ai\Tools\NodeTool;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentToolBinding;
use App\Models\Runs\Run;
use App\Models\User;
use App\Nodes\DataTransform\CallApiNode;
use App\Services\Workspaces\WorkspaceService;

it('exposes the tools it was constructed with to the SDK', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id]);

    $binding = new AgentToolBinding(['node_type' => 'call_api', 'config' => [], 'exposed_fields' => []]);
    $tool = new NodeTool(new CallApiNode, $binding, Run::factory()->create());

    $workspaceAgent = new WorkspaceAgent($agent->instructions, $session, tools: [$tool]);

    expect(iterator_to_array($workspaceAgent->tools()))->toBe([$tool]);
});

it('builds an agent with no tools when none are attached', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id]);

    $workspaceAgent = new WorkspaceAgent($agent->instructions, $session);

    expect(iterator_to_array($workspaceAgent->tools()))->toBe([]);
});
