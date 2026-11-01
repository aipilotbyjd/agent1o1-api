<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

it('waits for every incoming branch before running the join node', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    // route -> {branch_a, branch_b} -> join -> after
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'route', 'type' => 'router', 'config' => ['conditions' => [
                ['path' => 'input.x', 'operator' => 'equals', 'value' => 1, 'result' => 'go'],
            ]]],
            ['key' => 'branch_a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'branch_b', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'join', 'type' => 'join_paths', 'config' => []],
            ['key' => 'after', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [
            ['from' => 'route', 'to' => 'branch_a', 'condition' => 'go'],
            ['from' => 'route', 'to' => 'branch_b', 'condition' => 'go'],
            ['from' => 'branch_a', 'to' => 'join'],
            ['from' => 'branch_b', 'to' => 'join'],
            ['from' => 'join', 'to' => 'after'],
        ],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['x' => 1]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    // Exactly one join NodeRun row exists, despite two incoming branches.
    expect($run->nodeRuns->where('key', 'join'))->toHaveCount(1);
    expect($run->nodeRuns->firstWhere('key', 'join')->status)->toBe(NodeRunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'after')->status)->toBe(NodeRunStatus::Completed);
});

it('skips the join node once every incoming branch is skipped', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'route', 'type' => 'router', 'config' => ['conditions' => [
                ['path' => 'input.x', 'operator' => 'equals', 'value' => 1, 'result' => 'go'],
            ]]],
            ['key' => 'branch_a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'branch_b', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'join', 'type' => 'join_paths', 'config' => []],
        ],
        'edges' => [
            ['from' => 'route', 'to' => 'branch_a', 'condition' => 'go'],
            ['from' => 'route', 'to' => 'branch_b', 'condition' => 'never'],
            ['from' => 'branch_a', 'to' => 'join'],
            ['from' => 'branch_b', 'to' => 'join'],
        ],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    // input.x = 2 matches neither condition, so route's result is 'default'
    // and BOTH branches are unreachable -> join has nothing to join.
    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['x' => 2]);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->where('key', 'join'))->toHaveCount(1);
    expect($run->nodeRuns->firstWhere('key', 'join')->status)->toBe(NodeRunStatus::Skipped);
});
