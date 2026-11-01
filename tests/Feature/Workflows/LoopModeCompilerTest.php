<?php

use App\Exceptions\WorkflowValidationException;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

it('compiles a node with config._loop into a loop node backed by a hidden child workflow', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => [
                'mapping' => ['n' => 'input.item'],
                '_loop' => ['items_path' => 'input.items'],
            ]],
        ],
        'edges' => [],
    ]);

    $version = $workflow->publishVersion(publisher: $owner);

    $compiledNode = collect($version->graph['nodes'])->firstWhere('key', 'a');
    expect($compiledNode['type'])->toBe('loop');
    expect($compiledNode['config']['items_path'])->toBe('input.items');
    expect($compiledNode['config']['max_concurrent'])->toBe(1);
    expect($compiledNode['config']['on_item_error'])->toBe('fail_fast');

    $child = Workflow::find($compiledNode['config']['workflow_id']);
    expect($child)->not->toBeNull();
    expect($child->is_internal)->toBeTrue();
    expect($child->workspace_id)->toBe($workspace->id);
    expect($child->currentVersion->graph['nodes'])->toBe([
        ['key' => 'item', 'type' => 'transform', 'config' => ['mapping' => ['n' => 'input.item']], 'pinned_data' => null],
    ]);
});

it('rejects loop mode with no items_path', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => [], '_loop' => []]]],
        'edges' => [],
    ]);

    expect(fn () => $workflow->publishVersion(publisher: $owner))
        ->toThrow(WorkflowValidationException::class);
});

it('rejects an invalid on_item_error value', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => [
            'mapping' => [],
            '_loop' => ['items_path' => 'input.items', 'on_item_error' => 'nope'],
        ]]],
        'edges' => [],
    ]);

    expect(fn () => $workflow->publishVersion(publisher: $owner))
        ->toThrow(WorkflowValidationException::class);
});

it('rejects loop mode on a flow-control node type', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'wait', 'config' => ['_loop' => ['items_path' => 'input.items']]]],
        'edges' => [],
    ]);

    expect(fn () => $workflow->publishVersion(publisher: $owner))
        ->toThrow(WorkflowValidationException::class);
});

it('reuses the same child workflow across republishes instead of creating a new one', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => [
            'mapping' => ['n' => 'input.item'],
            '_loop' => ['items_path' => 'input.items'],
        ]]],
        'edges' => [],
    ]);
    $firstVersion = $workflow->publishVersion(publisher: $owner);
    $firstChildId = collect($firstVersion->graph['nodes'])->firstWhere('key', 'a')['config']['workflow_id'];

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => [
            'mapping' => ['n' => 'input.item', 'extra' => 'input.other'],
            '_loop' => ['items_path' => 'input.items'],
        ]]],
        'edges' => [],
    ]);
    $secondVersion = $workflow->publishVersion(publisher: $owner);
    $secondChildId = collect($secondVersion->graph['nodes'])->firstWhere('key', 'a')['config']['workflow_id'];

    expect($secondChildId)->toBe($firstChildId);
    expect(Workflow::where('is_internal', true)->count())->toBe(1);

    $child = Workflow::find($firstChildId);
    expect($child->currentVersion->graph['nodes'][0]['config']['mapping'])->toBe(['n' => 'input.item', 'extra' => 'input.other']);
});
