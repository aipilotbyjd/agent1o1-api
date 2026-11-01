<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Str;

function childWorkflowFor(Workspace $workspace, User $owner, array $nodes, array $edges = []): Workflow
{
    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph(['nodes' => $nodes, 'edges' => $edges]);
    $child->publishVersion(publisher: $owner);

    return $child->fresh();
}

it('runs a child workflow and resumes the parent with its output', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $child = childWorkflowFor($workspace, $owner, [
        ['key' => 'c', 'type' => 'transform', 'config' => ['mapping' => ['doubled' => 'input.n']]],
    ]);

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [
            ['key' => 'sub', 'type' => 'subflow', 'config' => ['workflow_id' => $child->id, 'input' => ['n' => 21]]],
            ['key' => 'after', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'sub', 'to' => 'after']],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    $subNode = $run->nodeRuns->firstWhere('key', 'sub');
    expect($subNode->status)->toBe(NodeRunStatus::Completed);
    expect($subNode->output)->toBe(['c' => ['doubled' => 21]]);
    expect($run->nodeRuns->firstWhere('key', 'after')->status)->toBe(NodeRunStatus::Completed);

    $childRun = Run::where('parent_node_id', $subNode->id)->sole();
    expect($childRun->workflow_id)->toBe($child->id);
    expect($childRun->status)->toBe(RunStatus::Completed);
});

it('fails the parent run when the child sub-workflow fails and there is no error edge', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $child = childWorkflowFor($workspace, $owner, [
        ['key' => 'c', 'type' => 'call_api', 'config' => ['method' => 'GET', 'url' => 'http://127.0.0.1:1/unreachable', 'timeout_seconds' => 1]],
    ]);

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'sub', 'type' => 'subflow', 'config' => ['workflow_id' => $child->id]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'sub')->status)->toBe(NodeRunStatus::Failed);
});

it('fails immediately when the referenced workflow_id does not exist', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'sub', 'type' => 'subflow', 'config' => ['workflow_id' => (string) Str::uuid()]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);
    $parent = $parent->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($parent);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'sub')->error)->toContain('does not exist');
});

it('refuses to run a published workflow from another workspace as its child', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Victim']);

    $foreignChild = childWorkflowFor($otherWorkspace, $owner, [
        ['key' => 'secret', 'type' => 'transform', 'config' => ['mapping' => []]],
    ]);

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'sub', 'type' => 'subflow', 'config' => ['workflow_id' => $foreignChild->id]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($parent->fresh())->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'sub')->error)->toContain('does not exist');
    expect(Run::where('workflow_id', $foreignChild->id)->exists())->toBeFalse();
});
