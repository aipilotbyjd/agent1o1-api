<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Tools\WorkflowTool;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Tools\Request;

it('starts a workflow run and reports its status back to the model', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['echoed' => 'input.value']]],
    ], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $tool = new WorkflowTool($workflow, app(StartWorkflowRunAction::class));

    $result = json_decode(unwrapUntrusted($tool->handle(new Request(['input' => ['value' => 'hi']]))), true);

    expect($result['status'])->toBe('completed');
    expect($result['output'])->toBe(['a' => ['echoed' => 'hi']]);
});

it('keeps a long workflow slug within the provider tool-name limit', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['slug' => str_repeat('very-long-slug-', 8)]);

    $name = (new WorkflowTool($workflow, app(StartWorkflowRunAction::class)))->name();

    expect(strlen($name))->toBeLessThanOrEqual(64)
        ->and($name)->toMatch('/^[A-Za-z0-9_-]+$/')
        ->and($name)->toEndWith("_{$workflow->id}");
});
