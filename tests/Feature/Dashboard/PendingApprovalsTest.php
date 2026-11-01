<?php

use App\Enums\RunStatus;
use App\Enums\Workspaces\Role;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowApproval;
use App\Services\Workspaces\WorkspaceService;
use Carbon\CarbonInterface;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

/**
 * A run paused on a `human_approval` node, with the approval row the engine
 * writes when it gets there.
 */
function pauseForApproval(Workflow $workflow, string $nodeKey, CarbonInterface|string $requestedAt): WorkflowApproval
{
    $run = Run::factory()->forWorkflow($workflow)->create([
        'status' => RunStatus::AwaitingApproval,
        'created_at' => $requestedAt,
    ]);

    $nodeRun = NodeRun::factory()->forRun($run)->create(['key' => $nodeKey, 'type' => 'human_approval']);

    return WorkflowApproval::query()->create([
        'run_id' => $run->id,
        'node_run_id' => $nodeRun->id,
        'requested_at' => $requestedAt,
    ]);
}

it('lists undecided approvals oldest first with the context to act on them', function () {
    $workspace = $this->workspace;
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Refund approval']);

    $newer = pauseForApproval($workflow, 'sign-off', now()->subHour());
    $older = pauseForApproval($workflow, 'gate', now()->subDays(3));

    Passport::actingAs($this->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/pending-approvals");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.id'))->toBe($older->id);
    expect($response->json('data.1.id'))->toBe($newer->id);
    expect($response->json('data.0.node.key'))->toBe('gate');
    expect($response->json('data.0.node.type'))->toBe('human_approval');
    expect($response->json('data.0.run.id'))->toBe($older->run_id);
    expect($response->json('data.0.run.status'))->toBe('awaiting_approval');
    expect($response->json('data.0.run.workflow.name'))->toBe('Refund approval');
    expect($response->json('data.0.waiting_seconds'))->toBeGreaterThanOrEqual(3 * 86400);
    expect($response->json('meta.total'))->toBe(2);
});

it('drops an approval once it has been decided', function () {
    $workspace = $this->workspace;
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $decided = pauseForApproval($workflow, 'gate', now()->subDay());
    $decided->forceFill(['decided_at' => now(), 'decision' => 'approve', 'decided_by' => $this->owner->id])->save();
    $stillWaiting = pauseForApproval($workflow, 'other-gate', now()->subHour());

    Passport::actingAs($this->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/pending-approvals");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($stillWaiting->id);
});

it('never lists an approval from another workspace', function () {
    $workspace = $this->workspace;
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);

    pauseForApproval(Workflow::factory()->forWorkspace($other)->create(), 'gate', now()->subHour());

    Passport::actingAs($this->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/pending-approvals");

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
    expect($response->json('meta.total'))->toBe(0);
});

it('lets a viewer read the queue they cannot decide', function () {
    $workspace = $this->workspace;
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    $approval = pauseForApproval(Workflow::factory()->forWorkspace($workspace)->create(), 'gate', now()->subHour());

    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/pending-approvals")
        ->assertOk()
        ->assertJsonPath('data.0.id', $approval->id);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$approval->run_id}/approvals/{$approval->id}/decide", [
        'decision' => 'approve',
    ])->assertForbidden();
});
