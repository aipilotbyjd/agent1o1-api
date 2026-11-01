<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Exceptions\CostLimitReachedException;
use App\Models\Billing\Plan;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\CreditGate;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * Dollar and concurrency limits that stop work before it starts. At the
 * default $0.005 per credit, 200 credits are $1.00.
 *
 * @param  array<string, mixed>  $limits
 */
function guardedWorkspace(array $limits, int $credits = 100000): Workspace
{
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => $credits, 'limits' => $limits]);
    config(['billing.default_plan' => 'free', 'billing.credit_value_usd' => 0.005]);

    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
}

function guardedWorkflow(Workspace $workspace): Workflow
{
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $workspace->owner);

    return $workflow->fresh();
}

it('refuses new work once the workspace reaches its daily AI cost cap', function () {
    $workspace = guardedWorkspace(['ai_cost_daily_usd' => 1]);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 'run-1', 200);

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace))
        ->toThrow(CostLimitReachedException::class, 'AI usage limit of $1.00 today');
});

it('lets work start again the next day', function () {
    $workspace = guardedWorkspace(['ai_cost_daily_usd' => 1]);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 'run-1', 200);

    $this->travel(1)->days();

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace))->not->toThrow(CostLimitReachedException::class);
});

it('refuses a workflow run past the plan\'s runs in flight', function () {
    $workspace = guardedWorkspace(['max_concurrent_runs' => 1]);
    $workflow = guardedWorkflow($workspace);
    Run::factory()->forWorkspace($workspace)->create(['runnable_id' => $workflow->id, 'status' => RunStatus::Running]);

    expect(fn () => app(StartWorkflowRunAction::class)->execute($workflow))
        ->toThrow(CostLimitReachedException::class, 'up to 1 workflows at a time');
});

it('pauses free workspaces once free usage across the platform passes the daily budget', function () {
    $first = guardedWorkspace([], credits: 1000);
    app(BillingSettings::class)->set('guardrails.free_tier_daily_budget_usd', 1);
    app(DeductCreditsAction::class)->execute($first, CreditTransactionType::NodeRun, 'run-1', 200);
    $second = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);

    expect(fn () => app(CreditGate::class)->assertCanStartRun($second))
        ->toThrow(CostLimitReachedException::class, 'paused for today');
});

it('returns 429 with the reason when a cost cap blocks a run from the API', function () {
    $workspace = guardedWorkspace(['ai_cost_daily_usd' => 1]);
    $workflow = guardedWorkflow($workspace);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 'run-1', 200);

    Passport::actingAs($workspace->owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/runs")
        ->assertTooManyRequests()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'AI usage limit'));
});

it('fails the next node without a retry once a run reaches its credit budget', function () {
    $workspace = guardedWorkspace(['max_run_credits' => 2]);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'c', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b', 'condition' => null], ['from' => 'b', 'to' => 'c', 'condition' => null]],
    ]);
    $workflow->publishVersion(publisher: $workspace->owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    $third = $run->nodeRuns()->where('key', 'c')->sole();
    expect($third->status)->toBe(NodeRunStatus::Failed)
        ->and($third->attempt)->toBe(1)
        ->and($third->error)->toContain('reached the 2-credit limit')
        ->and($run->nodeRuns()->where('key', 'b')->sole()->status)->toBe(NodeRunStatus::Completed);
});
