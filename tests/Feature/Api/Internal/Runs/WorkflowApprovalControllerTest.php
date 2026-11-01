<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('approves a pending approval through the internal api', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph(['nodes' => [['key' => 'gate', 'type' => 'human_approval', 'config' => []]], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $approval = $run->fresh(['nodeRuns'])->nodeRuns->first()->approval;

    Passport::actingAs($owner);

    $response = $this->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/approvals/{$approval->id}/decide",
        ['decision' => 'approve', 'message' => 'ship it'],
    );

    $response->assertOk();
    expect($response->json('data.approval.decision'))->toBe('approved');
    expect($run->fresh()->status->value)->toBe('completed');
});

it('404s deciding an approval that belongs to a different run', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'gate', 'type' => 'human_approval', 'config' => []]], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $runA = app(StartWorkflowRunAction::class)->execute($workflow);
    $runB = app(StartWorkflowRunAction::class)->execute($workflow);
    $approvalForA = $runA->fresh(['nodeRuns'])->nodeRuns->first()->approval;

    Passport::actingAs($owner);

    $this->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$runB->id}/approvals/{$approvalForA->id}/decide",
        ['decision' => 'approve'],
    )->assertNotFound();
});
