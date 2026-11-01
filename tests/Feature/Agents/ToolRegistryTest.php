<?php

use App\Ai\Tools\ExportArtifactTool;
use App\Ai\Tools\ForgetTool;
use App\Ai\Tools\NodeTool;
use App\Ai\Tools\RememberTool;
use App\Ai\Tools\UpdateInstructionsTool;
use App\Ai\Tools\WorkflowTool;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Ai\ModelCatalog;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Agents\ToolRegistry;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;

it('builds one NodeTool per attached node and one WorkflowTool per attached workflow', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['provider' => 'mistral']);
    $run = Run::factory()->create();

    $agent->toolBindings()->create([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://internal.example.com', 'method' => 'GET'],
        'exposed_fields' => [],
    ]);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $agent->workflows()->attach($workflow->id);

    $tools = app(ToolRegistry::class)->toolsFor($agent, $run);

    expect($tools)->toHaveCount(4);
    expect($tools[0])->toBeInstanceOf(NodeTool::class);
    expect($tools[0]->name())->toBe('call_api');
    expect($tools[1])->toBeInstanceOf(WorkflowTool::class);
    expect($tools[2])->toBeInstanceOf(RememberTool::class);
    expect($tools[3])->toBeInstanceOf(ForgetTool::class);
});

it('silently skips a binding whose node type is no longer registered', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['provider' => 'mistral']);
    $run = Run::factory()->create();

    $agent->toolBindings()->create(['node_type' => 'custom:999999', 'config' => [], 'exposed_fields' => []]);

    $tools = app(ToolRegistry::class)->toolsFor($agent, $run);

    expect($tools)->toHaveCount(2);
    expect($tools[0])->toBeInstanceOf(RememberTool::class);
    expect($tools[1])->toBeInstanceOf(ForgetTool::class);
});

it('attaches ExportArtifactTool only for a session-backed run', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['provider' => 'mistral', 'allow_skill_editing' => false, 'allow_self_clone' => false]);
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $sessionRun = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);

    $tools = app(ToolRegistry::class)->toolsFor($agent, $sessionRun);
    expect($tools)->toHaveCount(3);
    expect($tools[0])->toBeInstanceOf(RememberTool::class);
    expect($tools[1])->toBeInstanceOf(ForgetTool::class);
    expect($tools[2])->toBeInstanceOf(ExportArtifactTool::class);

    $workflowRun = Run::factory()->forWorkspace($workspace)->create();
    $tools = app(ToolRegistry::class)->toolsFor($agent, $workflowRun);
    expect($tools)->toHaveCount(2);
    expect($tools[0])->toBeInstanceOf(RememberTool::class);
    expect($tools[1])->toBeInstanceOf(ForgetTool::class);
});

it('offers the self-update tool only in a conversation with an agent that allows it', function (bool $allowed, bool $inConversation, bool $offered) {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['allow_self_updates' => $allowed]);
    $session = $inConversation ? AgentSession::factory()->forAgent($agent)->create() : null;

    $tools = app(ToolRegistry::class)->toolsFor($agent, Run::factory()->create(), $session);

    expect(collect($tools)->contains(fn ($tool) => $tool instanceof UpdateInstructionsTool))->toBe($offered);
})->with([
    'allowed, in a conversation' => [true, true, true],
    'allowed, stateless eval call' => [true, false, false],
    'not allowed' => [false, true, false],
]);

it('offers native web search and fetch only when every provider in the chain runs them', function (string $provider, array $expected) {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['provider' => $provider]);

    $tools = app(ToolRegistry::class)->toolsFor($agent, Run::factory()->create());

    expect(collect($tools)->filter(fn ($tool) => $tool instanceof WebSearch || $tool instanceof WebFetch)->map(fn ($tool) => $tool::class)->values()->all())
        ->toBe($expected);
})->with([
    'anthropic runs both' => ['anthropic', [WebSearch::class, WebFetch::class]],
    'openai runs search only' => ['openai', [WebSearch::class]],
    'an openai-compatible gateway runs neither' => ['xkiro', []],
]);

it('withholds a web tool when any provider in the failover chain lacks it', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $catalog = ModelCatalog::factory()->create();
    $catalog->routes()->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-sonnet-5', 'priority' => 1, 'is_enabled' => true]);
    $catalog->routes()->create(['execution_provider' => 'xkiro', 'execution_model_id' => 'mistral-small-4', 'priority' => 2, 'is_enabled' => true]);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['model_catalog_id' => $catalog->id]);

    $tools = app(ToolRegistry::class)->toolsFor($agent, Run::factory()->create());

    expect(collect($tools)->contains(fn ($tool) => $tool instanceof WebSearch || $tool instanceof WebFetch))->toBeFalse();
});
