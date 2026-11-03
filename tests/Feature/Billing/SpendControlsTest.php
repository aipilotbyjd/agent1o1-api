<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Billing\SpendKillSwitch;
use App\Enums\NodeRunStatus;
use App\Exceptions\CostLimitReachedException;
use App\Exceptions\InsufficientCreditsException;
use App\Jobs\Billing\ChargeNodeRunJob;
use App\Models\Ai\AiCallEvent;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\CreditGate;
use App\Services\Billing\CreditReservations;
use App\Services\Billing\SpendControls;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    config(['billing.default_plan' => 'free', 'billing.credit_value_usd' => 0.005]);
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 1000, 'limits' => []]);
});

function spendWorkspace(): Workspace
{
    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => fake()->company()]);
}

it('stops free and trial workspaces, or everyone, with the kill switch', function () {
    $workspace = spendWorkspace();
    $controls = app(SpendControls::class);

    $controls->setKillSwitch(SpendKillSwitch::FreeAndTrial);
    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace))->toThrow(CostLimitReachedException::class, 'paused for a short time');

    $controls->setKillSwitch(SpendKillSwitch::Off);
    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace))->not->toThrow(CostLimitReachedException::class);

    expect($controls->killSwitch())->toBe(SpendKillSwitch::Off);
});

it('stops free workspaces once the platform\'s provider spend reaches the daily budget, and everyone at the hard stop', function () {
    $workspace = spendWorkspace();
    app(BillingSettings::class)->set('guardrails.platform_daily_budget_usd', 10);
    app(BillingSettings::class)->set('guardrails.platform_hard_stop_ratio', 2);

    AiCallEvent::factory()->costing(9)->create();
    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace))->not->toThrow(CostLimitReachedException::class);

    AiCallEvent::factory()->costing(2)->create();
    cache()->flush();
    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace))->toThrow(CostLimitReachedException::class, 'Free and trial usage is paused');

    AiCallEvent::factory()->costing(10)->create();
    cache()->flush();
    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace))->toThrow(CostLimitReachedException::class, 'very high demand');
});

it('ignores spend on a workspace\'s own key when measuring the platform budget', function () {
    app(BillingSettings::class)->set('guardrails.platform_daily_budget_usd', 10);
    AiCallEvent::factory()->costing(50)->create(['is_byok' => true]);

    expect(app(SpendControls::class)->platformSpendTodayUsd())->toBe(0.0);
});

it('pauses one throttled workspace until its throttle runs out', function () {
    $workspace = spendWorkspace();
    app(SpendControls::class)->throttle($workspace->id, now()->addHour(), 'Spike');

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace))->toThrow(CostLimitReachedException::class, 'unusual activity')
        ->and(app(SpendControls::class)->currentThrottle($workspace->id)['reason'])->toBe('Spike');

    $this->travel(2)->hours();

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace))->not->toThrow(CostLimitReachedException::class);
});

it('measures a workspace\'s daily cap against its real provider cost when that is higher than its credits', function () {
    Plan::query()->where('slug', 'free')->update(['limits' => ['ai_cost_daily_usd' => 1]]);
    $workspace = spendWorkspace();
    AiCallEvent::factory()->forWorkspace($workspace->id)->costing(1.25)->create();

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace))->toThrow(CostLimitReachedException::class, 'AI usage limit');
});

it('counts credits held for work in flight against the balance', function () {
    $workspace = spendWorkspace();
    $workspace->currentUsagePeriod()->update(['credits_limit' => 30]);
    $reservations = app(CreditReservations::class);

    $reservations->hold($workspace->id, 'run', 'r1', 30);
    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace->fresh()))->toThrow(InsufficientCreditsException::class);

    $reservations->release('run', 'r1');
    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace->fresh()))->not->toThrow(InsufficientCreditsException::class);
});

it('lets an expired hold stop counting, and releases a hold when its work throws', function () {
    $workspace = spendWorkspace();
    $reservations = app(CreditReservations::class);
    $reservations->hold($workspace->id, 'run', 'old', 50);

    expect($reservations->outstanding($workspace->id))->toBe(50);

    try {
        $reservations->holding($workspace->id, 'run', 'r2', 20, fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
    }

    expect($reservations->outstanding($workspace->id))->toBe(50);

    $this->travel(config('billing.reservations.ttl_minutes') + 1)->minutes();

    expect($reservations->outstanding($workspace->id))->toBe(0);
});

it('keeps a run\'s running credit total as its steps finish and charges each one as it finishes', function () {
    config(['billing.realtime_node_billing' => true]);
    $workspace = spendWorkspace();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b', 'condition' => null]],
    ]);
    $workflow->publishVersion(publisher: $workspace->owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    expect(Run::query()->find($run->id)->credits_accrued)->toBe(2)
        ->and(CreditTransaction::query()->where('workspace_id', $workspace->id)->where('source_type', CreditTransactionType::NodeRun)->count())->toBe(2);
});

it('charges a finished step once even when the end-of-run charge runs too', function () {
    $workspace = spendWorkspace();
    $run = Run::factory()->forWorkspace($workspace)->create();
    $nodeRun = NodeRun::factory()->create(['run_id' => $run->id, 'key' => 'a', 'type' => 'transform', 'status' => NodeRunStatus::Completed, 'finished_at' => now()]);

    ChargeNodeRunJob::dispatchSync($nodeRun->id);
    ChargeNodeRunJob::dispatchSync($nodeRun->id);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, $nodeRun->id, 1, allowOverdraft: true);

    expect(CreditTransaction::query()->where('source_id', $nodeRun->id)->count())->toBe(1);
});
