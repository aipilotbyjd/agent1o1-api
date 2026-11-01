<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Agents\Agent;
use App\Models\Billing\Plan;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Notifications\Billing\CreditsExhaustedNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

/**
 * The pre-flight balance check. Credits are metered after the fact, so
 * without this a workspace at zero still executed its whole DAG (and paid
 * the LLM bill) before the charge was even attempted.
 */
function exhaustedWorkspace(): Workspace
{
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 10]);
    config(['billing.default_plan' => 'free']);

    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $workspace->currentUsagePeriod()->increment('credits_used', 10);

    return $workspace->fresh();
}

function publishedWorkflowFor(Workspace $workspace): Workflow
{
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $workspace->owner);

    return $workflow->fresh();
}

it('refuses to start a workflow run when the workspace is out of credits', function () {
    $workspace = exhaustedWorkspace();
    $workflow = publishedWorkflowFor($workspace);

    expect(fn () => app(StartWorkflowRunAction::class)->execute($workflow))
        ->toThrow(InsufficientCreditsException::class);

    // Refused before any Run row or node execution, not after the spend.
    expect(Run::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('notifies workspace owners when credits are exhausted', function () {
    Notification::fake();
    $workspace = exhaustedWorkspace();
    $workflow = publishedWorkflowFor($workspace);

    expect(fn () => app(StartWorkflowRunAction::class)->execute($workflow))
        ->toThrow(InsufficientCreditsException::class);

    Notification::assertSentTo($workspace->owner, CreditsExhaustedNotification::class);
});

it('allows a workflow run while credits remain', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 10]);
    config(['billing.default_plan' => 'free']);
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

    $run = app(StartWorkflowRunAction::class)->execute(publishedWorkflowFor($workspace));

    expect($run)->not->toBeNull();
});

it('lets the last credit through, then refuses the next run', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 1]);
    config(['billing.default_plan' => 'free']);
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $workflow = publishedWorkflowFor($workspace);

    app(StartWorkflowRunAction::class)->execute($workflow);

    expect($workspace->fresh()->availableCredits())->toBe(0);
    expect(fn () => app(StartWorkflowRunAction::class)->execute($workflow))
        ->toThrow(InsufficientCreditsException::class);
});

it('spends top-up credits once the plan allowance is exhausted', function () {
    $workspace = exhaustedWorkspace();
    $workspace->increment('topup_credits', 5);
    $workflow = publishedWorkflowFor($workspace);

    $run = app(StartWorkflowRunAction::class)->execute($workflow);

    expect($run)->not->toBeNull();
    expect($workspace->fresh()->topup_credits)->toBe(4);
});

it('refuses an agent turn when the workspace is out of credits', function () {
    $workspace = exhaustedWorkspace();
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $workspace->owner);

    expect(fn () => app(SendAgentMessageAction::class)->execute($session, 'hello'))
        ->toThrow(InsufficientCreditsException::class);

    expect(Run::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('never gates a workspace whose plan is unlimited', function () {
    Plan::query()->delete();
    config(['billing.default_plan' => 'free']);
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

    expect(app(StartWorkflowRunAction::class)->execute(publishedWorkflowFor($workspace)))->not->toBeNull();
});

it('maps an exhausted balance to a 402 on the run endpoint', function () {
    $workspace = exhaustedWorkspace();
    $workflow = publishedWorkflowFor($workspace);

    Passport::actingAs($workspace->owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/runs")
        ->assertStatus(402);
});

it('does not gate a sub-workflow child run mid-flight', function () {
    $workspace = exhaustedWorkspace();
    $parent = publishedWorkflowFor($workspace);
    $child = publishedWorkflowFor($workspace);

    $parentRun = Run::factory()->create([
        'workspace_id' => $workspace->id,
        'runnable_type' => Workflow::class,
        'runnable_id' => $parent->id,
        'workflow_id' => $parent->id,
    ]);

    $parentNode = $parentRun->nodeRuns()->create([
        'workspace_id' => $workspace->id,
        'key' => 'sub',
        'type' => 'sub_workflow',
    ]);

    // The parent already passed the gate; refusing its child here would strand
    // the parent run without saving any spend.
    expect(app(StartWorkflowRunAction::class)->execute($child, parentNode: $parentNode))->not->toBeNull();
});
