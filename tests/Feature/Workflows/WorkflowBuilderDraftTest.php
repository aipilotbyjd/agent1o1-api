<?php

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Ai\Tools\WorkflowBuilder\AddNodeTool;
use App\Ai\Tools\WorkflowBuilder\ConnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\DisconnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\DryRunWorkflowTool;
use App\Ai\Tools\WorkflowBuilder\InspectNodeSchemaTool;
use App\Ai\Tools\WorkflowBuilder\ReadDraftTool;
use App\Ai\Tools\WorkflowBuilder\RemoveNodeTool;
use App\Ai\Tools\WorkflowBuilder\UpdateNodeTool;
use App\Enums\Workflows\BuilderSessionStatus;
use App\Exceptions\WorkflowBuilderConflictException;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request as ToolRequest;

function builderDraftSession(array $graph = ['nodes' => [], 'edges' => []]): WorkflowBuilderSession
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create(['draft_graph' => $graph]);
}

function transformNode(string $key): array
{
    return ['key' => $key, 'type' => 'transform', 'config' => ['mapping' => []], 'position' => null];
}

it('labels every snapshot with the edit that made it', function () {
    $session = builderDraftSession();

    $session->addNode('fetch', 'transform', ['mapping' => []]);
    $session->addNode('send', 'transform', ['mapping' => []]);
    $session->connect('fetch', 'send');
    $session->disconnect('fetch', 'send');
    $session->removeNode('send');

    expect($session->draftVersions()->oldest()->oldest('id')->pluck('label')->all())->toBe([
        'Added node fetch',
        'Added node send',
        'Connected fetch → send',
        'Disconnected fetch → send',
        'Removed node send',
    ]);
    expect($session->fresh()->draft_lock_version)->toBe(5);
});

it('refuses an edit from a copy of the session that is behind the draft', function () {
    $session = builderDraftSession();
    $stale = WorkflowBuilderSession::find($session->id);

    $session->addNode('fetch', 'transform', ['mapping' => []]);

    expect(fn () => $stale->addNode('other', 'transform', ['mapping' => []]))
        ->toThrow(WorkflowBuilderConflictException::class);

    expect($session->fresh()->currentGraph()['nodes'])->toHaveCount(1);
    expect($session->draftVersions()->count())->toBe(1);
});

it('replaces the draft from the canvas when the lock version is current', function () {
    $session = builderDraftSession();

    $session->replaceDraft([
        'nodes' => [transformNode('a'), transformNode('b')],
        'edges' => [['from' => 'a', 'to' => 'b']],
    ], 0);

    $session->refresh();
    expect($session->currentGraph()['nodes'])->toHaveCount(2);
    expect($session->currentGraph()['edges'][0])->toBe(['from' => 'a', 'to' => 'b', 'condition' => null]);
    expect($session->draftVersions()->value('label'))->toBe('Synced from canvas');
});

it('refuses a canvas sync from behind the draft', function () {
    $session = builderDraftSession();
    $session->addNode('fetch', 'transform', ['mapping' => []]);

    expect(fn () => $session->replaceDraft(['nodes' => [], 'edges' => []], 0))
        ->toThrow(WorkflowBuilderConflictException::class);

    expect($session->fresh()->currentGraph()['nodes'])->toHaveCount(1);
});

it('rejects a canvas sync with an invalid graph', function (array $graph, string $message) {
    $session = builderDraftSession();

    expect(fn () => $session->replaceDraft($graph, 0))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'duplicate keys' => [['nodes' => [transformNode('a'), transformNode('a')], 'edges' => []], "Duplicate node key 'a'"],
    'unknown type' => [['nodes' => [['key' => 'a', 'type' => 'nope', 'config' => []]], 'edges' => []], 'no node for type'],
    'dangling edge' => [['nodes' => [transformNode('a')], 'edges' => [['from' => 'a', 'to' => 'ghost']]], "unknown target node 'ghost'"],
]);

it('restores an earlier snapshot as a new, undoable snapshot', function () {
    $session = builderDraftSession();
    $session->addNode('fetch', 'transform', ['mapping' => []]);
    $firstVersion = $session->draftVersions()->first();
    $session->addNode('send', 'transform', ['mapping' => []]);

    $session->restoreVersion($firstVersion);

    expect(array_column($session->fresh()->currentGraph()['nodes'], 'key'))->toBe(['fetch']);
    expect($session->draftVersions()->count())->toBe(3);
    expect($session->draftVersions()->latest()->latest('id')->value('label'))->toBe('Restored Added node fetch');
});

it('refuses to edit an archived session', function () {
    $session = builderDraftSession();
    $session->update(['status' => BuilderSessionStatus::Archived]);

    expect(fn () => $session->assertEditable())->toThrow(WorkflowBuilderConflictException::class);
});

it('reads the current draft, including edits made since the tool was built', function () {
    $session = builderDraftSession(['nodes' => [transformNode('a')], 'edges' => []]);
    $tool = new ReadDraftTool($session);

    WorkflowBuilderSession::find($session->id)->addNode('b', 'transform', ['mapping' => []]);

    $draft = json_decode((string) $tool->handle(new ToolRequest([], 'call-1')), true);

    expect(array_column($draft['nodes'], 'key'))->toBe(['a', 'b']);
    expect($draft['entry_nodes'])->toBe(['a', 'b']);
});

it('tells the model to re-read the draft when the user changed it mid-turn', function () {
    $session = builderDraftSession(['nodes' => [transformNode('a'), transformNode('b')], 'edges' => []]);
    $tools = collect(iterator_to_array((new WorkflowBuilderAgent($session))->tools()))->keyBy(fn ($tool) => $tool->name());

    // The canvas saves while the agent is mid-turn.
    WorkflowBuilderSession::find($session->id)->removeNode('b');

    $result = (string) $tools['connect_nodes']->handle(new ToolRequest(['from' => 'a', 'to' => 'b'], 'call-1'));

    expect($tools['connect_nodes'])->toBeInstanceOf(ConnectNodesTool::class);
    expect($result)->toContain('read_draft');
    // The session was reloaded, so the retry works from the user's draft.
    expect(array_column($session->currentGraph()['nodes'], 'key'))->toBe(['a']);
});

it('reports a missing edge instead of recording an empty edit', function () {
    $session = builderDraftSession(['nodes' => [transformNode('a'), transformNode('b')], 'edges' => []]);

    $result = (string) (new DisconnectNodesTool($session))->handle(new ToolRequest(['from' => 'a', 'to' => 'b'], 'call-1'));

    expect($result)->toContain('There is no edge');
    expect($session->draftVersions()->count())->toBe(0);
});

it('stores a repeated edge from the canvas once', function () {
    $session = builderDraftSession();

    $session->replaceDraft([
        'nodes' => [transformNode('a'), transformNode('b')],
        'edges' => [['from' => 'a', 'to' => 'b'], ['from' => 'a', 'to' => 'b']],
    ], 0);

    expect($session->fresh()->currentGraph()['edges'])->toBe([['from' => 'a', 'to' => 'b', 'condition' => null]]);
});

it('disconnects only the edge with a given condition, turning an always-edge into an error path', function () {
    $session = builderDraftSession([
        'nodes' => [transformNode('a'), transformNode('b')],
        'edges' => [['from' => 'a', 'to' => 'b', 'condition' => null]],
    ]);
    $session->connect('a', 'b', 'error');

    $result = (string) (new DisconnectNodesTool($session))->handle(new ToolRequest(['from' => 'a', 'to' => 'b', 'condition' => 'none'], 'call-1'));

    expect($result)->toBe('Disconnected [a] from [b].');
    expect($session->fresh()->currentGraph()['edges'])->toBe([['from' => 'a', 'to' => 'b', 'condition' => 'error']]);

    $missing = (string) (new DisconnectNodesTool($session))->handle(new ToolRequest(['from' => 'a', 'to' => 'b', 'condition' => 'none'], 'call-2'));
    expect($missing)->toContain('with that condition');
});

it('hands a malformed tool call back to the model instead of failing the turn', function (string $tool, array $arguments, string $message) {
    $session = builderDraftSession(['nodes' => [transformNode('a')], 'edges' => []]);

    $result = (string) (new $tool($session))->handle(new ToolRequest($arguments, 'call-1'));

    expect($result)->toContain($message);
    expect($session->fresh()->draft_lock_version)->toBe(0);
})->with([
    'config_json as a number' => [AddNodeTool::class, ['key' => 'b', 'type' => 'transform', 'config_json' => '5'], 'must be a JSON object'],
    'config_json as a list' => [AddNodeTool::class, ['key' => 'b', 'type' => 'transform', 'config_json' => '[1, 2]'], 'must be a JSON object'],
    'config_json not JSON' => [AddNodeTool::class, ['key' => 'b', 'type' => 'transform', 'config_json' => '{nope'], 'valid JSON object string'],
    'missing key' => [AddNodeTool::class, ['type' => 'transform'], 'The key argument is required'],
    'key as an object' => [RemoveNodeTool::class, ['key' => ['a']], 'The key argument must be a string'],
    'missing edge end' => [ConnectNodesTool::class, ['from' => 'a'], 'The to argument is required'],
    'remove_fields as numbers' => [UpdateNodeTool::class, ['key' => 'a', 'remove_fields' => [1]], 'must be a list of strings'],
    'dry-run input as a string' => [DryRunWorkflowTool::class, ['sample_input_json' => '"hi"'], 'must be a JSON object'],
    'schema type as an object' => [InspectNodeSchemaTool::class, ['type' => ['transform']], 'The type argument must be a string'],
]);

it('accepts config_json sent as an object as well as a JSON string', function () {
    $session = builderDraftSession();
    $tool = new AddNodeTool($session);

    expect((string) $tool->handle(new ToolRequest(['key' => 'a', 'type' => 'transform', 'config_json' => ['mapping' => ['x' => 1]]], 'call-1')))->toBe('Added node [a].');
    expect((string) $tool->handle(new ToolRequest(['key' => 'b', 'type' => 'transform', 'config_json' => '{"mapping": {}}'], 'call-2')))->toBe('Added node [b].');

    expect($session->fresh()->currentGraph()['nodes'][0]['config'])->toBe(['mapping' => ['x' => 1]]);
});

it('reports an unexpected failure inside a tool as its result', function () {
    $session = builderDraftSession();

    DB::listen(function ($query): void {
        if (str_contains($query->sql, 'workflow_builder_draft_versions') && str_starts_with(strtolower($query->sql), 'insert')) {
            throw new RuntimeException('database went away');
        }
    });

    $result = (string) (new AddNodeTool($session))->handle(new ToolRequest(['key' => 'a', 'type' => 'transform', 'config_json' => '{"mapping": {}}'], 'call-1'));

    expect($result)->toBe('add_node failed unexpectedly, so nothing was changed. Check the arguments and try again.');
    expect($session->fresh()->draft_lock_version)->toBe(0);
});

it('removes config fields a merge cannot, such as loop mode', function () {
    $session = builderDraftSession(['nodes' => [
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => [], '_loop' => ['items_path' => 'input.rows']], 'position' => null],
    ], 'edges' => []]);

    $result = (string) (new UpdateNodeTool($session))->handle(new ToolRequest(['key' => 'a', 'remove_fields' => ['_loop']], 'call-1'));

    expect($result)->toBe('Updated node [a].');
    expect($session->fresh()->currentGraph()['nodes'][0]['config'])->toBe(['mapping' => []]);
});

it('refuses an update that changes nothing, or sets and removes the same field', function () {
    $session = builderDraftSession(['nodes' => [transformNode('a')], 'edges' => []]);

    expect(fn () => $session->updateNode('a', []))->toThrow(InvalidArgumentException::class, 'Nothing to update');
    expect(fn () => $session->updateNode('a', ['mapping' => []], removeFields: ['mapping']))->toThrow(InvalidArgumentException::class, "can't be both set and removed: mapping");
    expect($session->draftVersions()->count())->toBe(0);
});

it('attributes the assistant\'s edits to the member who asked for them', function () {
    $session = builderDraftSession();
    $colleague = User::factory()->create();

    $tools = collect(iterator_to_array((new WorkflowBuilderAgent($session, actingUser: $colleague))->tools()))->keyBy(fn ($tool) => $tool->name());
    $tools['add_node']->handle(new ToolRequest(['key' => 'a', 'type' => 'transform', 'config_json' => '{"mapping": {}}'], 'call-1'));

    expect($session->draftVersions()->value('triggered_by'))->toBe($colleague->id);
});

it('stops the assistant editing a session archived mid-turn', function () {
    $session = builderDraftSession();
    $tool = new AddNodeTool($session);

    WorkflowBuilderSession::whereKey($session->id)->update(['status' => BuilderSessionStatus::Archived]);

    $result = (string) $tool->handle(new ToolRequest(['key' => 'a', 'type' => 'transform', 'config_json' => '{"mapping": {}}'], 'call-1'));

    expect($result)->toContain('This session is archived');
    expect($session->fresh()->currentGraph()['nodes'])->toBe([]);
});

it('keeps only the newest draft versions', function () {
    $session = builderDraftSession();

    foreach (range(1, WorkflowBuilderSession::MAX_DRAFT_VERSIONS + 3) as $i) {
        $session->addNode("n{$i}", 'transform', ['mapping' => []]);
    }

    expect($session->draftVersions()->count())->toBe(WorkflowBuilderSession::MAX_DRAFT_VERSIONS);
    expect($session->draftVersions()->latest()->latest('id')->value('label'))->toBe('Added node n'.(WorkflowBuilderSession::MAX_DRAFT_VERSIONS + 3));
    expect($session->draftVersions()->where('label', 'Added node n3')->exists())->toBeFalse();
    expect($session->draftVersions()->where('label', 'Added node n4')->exists())->toBeTrue();
});

it('refuses a restore from behind the draft when the caller says what it last saw', function () {
    $session = builderDraftSession();
    $session->addNode('a', 'transform', ['mapping' => []]);
    $version = $session->draftVersions()->first();
    $session->addNode('b', 'transform', ['mapping' => []]);

    expect(fn () => $session->restoreVersion($version, expectedLockVersion: 1))->toThrow(WorkflowBuilderConflictException::class);

    $session->restoreVersion($version, expectedLockVersion: 2);

    expect(array_column($session->fresh()->currentGraph()['nodes'], 'key'))->toBe(['a']);
});

it('lets the canvas save around node types it cannot check, but not add new unknown ones', function () {
    $session = builderDraftSession(['nodes' => [
        ['key' => 'legacy', 'type' => 'retired_type', 'config' => [], 'position' => null],
    ], 'edges' => []]);

    $session->replaceDraft(['nodes' => [
        ['key' => 'legacy', 'type' => 'retired_type', 'config' => [], 'position' => ['x' => 5, 'y' => 5]],
        ['key' => 'custom', 'type' => 'custom:0197a4c2-0000-7000-8000-000000000000', 'config' => []],
    ], 'edges' => [['from' => 'legacy', 'to' => 'custom']]], 0);

    expect(array_column($session->fresh()->currentGraph()['nodes'], 'key'))->toBe(['legacy', 'custom']);

    expect(fn () => $session->replaceDraft(['nodes' => [
        ['key' => 'legacy', 'type' => 'other_retired_type', 'config' => []],
    ], 'edges' => []], 1))->toThrow(InvalidArgumentException::class, 'no node for type [other_retired_type]');
});

it('says a canvas sync was refused because the session was archived, not because the draft moved', function () {
    $session = builderDraftSession();

    WorkflowBuilderSession::whereKey($session->id)->update(['status' => BuilderSessionStatus::Archived]);

    expect(fn () => $session->replaceDraft(['nodes' => [transformNode('a')], 'edges' => []], 0))
        ->toThrow(WorkflowBuilderConflictException::class, 'This session is archived');
});
