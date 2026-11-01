<?php

use App\Ai\Tools\WorkflowBuilder\InspectNodeOutputTool;
use App\Broadcasting\WorkspaceChannelGate;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workflows\DryRunner;
use App\Services\Workflows\NodeOutputShapes;
use App\Services\Workflows\OutputSchemaInferrer;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Tools\Request as ToolRequest;

it('infers field types across samples without keeping any values', function () {
    $schema = app(OutputSchemaInferrer::class)->infer([
        ['id' => 1, 'email' => 'a@example.com', 'tags' => ['vip'], 'profile' => ['name' => 'Ada']],
        ['id' => 2, 'email' => null, 'tags' => [], 'profile' => ['name' => 'Bob', 'age' => 36]],
    ]);

    expect($schema)->toBe([
        'id' => ['type' => 'integer'],
        'email' => ['type' => 'string'],
        'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
        'profile' => ['type' => 'object', 'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
        ]],
    ]);
    expect(json_encode($schema))->not->toContain('Ada')->not->toContain('example.com');
});

it('learns a node type\'s output shape from the workspace\'s own runs only', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $other = app(WorkspaceService::class)->create($owner, ['name' => 'Other']);

    NodeRun::factory()->forRun(Run::factory()->forWorkspace($workspace)->create())
        ->completed(['status_code' => 200, 'body' => ['ok' => true]])
        ->create(['type' => 'call_api']);
    NodeRun::factory()->forRun(Run::factory()->forWorkspace($other)->create())
        ->completed(['secret_field' => 'x'])
        ->create(['type' => 'call_api']);

    $shapes = app(NodeOutputShapes::class);

    expect(array_keys($shapes->schemaFor($workspace, 'call_api')))->toBe(['status_code', 'body']);
    expect($shapes->schemaFor($workspace, 'transform'))->toBeNull();
});

it('checks dry-run references against output shapes learnt in the workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    NodeRun::factory()->forRun(Run::factory()->forWorkspace($workspace)->create())
        ->completed(['result' => 'x'])
        ->create(['type' => 'run_code']);

    $graph = [
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => ['operations' => [['op' => 'set', 'output' => 'result', 'value' => '1']]]],
            ['key' => 'b', 'type' => 'run_code', 'config' => ['operations' => [
                ['op' => 'set', 'output' => 'ok', 'value' => '{{ nodes.a.result }}'],
                ['op' => 'set', 'output' => 'typo', 'value' => '{{ nodes.a.reslt }}'],
            ]]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b', 'condition' => null]],
    ];

    $result = app(DryRunner::class)->run($graph, [], $workspace);

    expect($result['warnings'])->toHaveCount(1);
    expect($result['warnings'][0])->toContain('nodes.a.reslt');
    expect($result['unverified'])->toBe([]);
});

it('lets the builder inspect a draft node\'s known output fields', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create([
        'draft_graph' => ['nodes' => [['key' => 'fetch', 'type' => 'call_api', 'config' => []]], 'edges' => []],
    ]);
    NodeRun::factory()->forRun(Run::factory()->forWorkspace($workspace)->create())
        ->completed(['status_code' => 200])
        ->create(['type' => 'call_api']);

    $tool = new InspectNodeOutputTool($session);

    $known = json_decode((string) $tool->handle(new ToolRequest(['key' => 'fetch'], 'call-1')), true);
    expect($known)->toBe(['type' => 'call_api', 'known' => true, 'fields' => ['status_code' => ['type' => 'integer']]]);

    $unknown = json_decode((string) $tool->handle(new ToolRequest(['type' => 'transform'], 'call-2')), true);
    expect($unknown['known'])->toBeFalse();

    expect((string) $tool->handle(new ToolRequest(['key' => 'ghost'], 'call-3')))->toContain('read_draft');
});

it('authorizes builder session channels for members of the session\'s workspace only', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $other = app(WorkspaceService::class)->create($owner, ['name' => 'Other']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    $gate = app(WorkspaceChannelGate::class);

    expect($gate->workflowBuilderSession($owner, $workspace->id, $session->id))->toBeTrue();
    expect($gate->workflowBuilderSession($stranger, $workspace->id, $session->id))->toBeFalse();
    expect($gate->workflowBuilderSession($owner, $other->id, $session->id))->toBeFalse();
});
