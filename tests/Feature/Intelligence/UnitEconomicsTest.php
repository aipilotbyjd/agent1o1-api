<?php

use App\Enums\Billing\CreditTransactionType;
use App\Models\Ai\AiCallEvent;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Intelligence\FactRollup;
use App\Services\Intelligence\UnitEconomics;
use App\Services\Workspaces\WorkspaceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['billing.default_plan' => 'free', 'billing.margin_floor.payment_fee_rate' => 0.0]);
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 1000]);
});

function economicsWorkspace(string $name): Workspace
{
    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => $name]);
}

function charge(Workspace $workspace, int $credits, float $revenue): void
{
    CreditTransaction::query()->create([
        'workspace_id' => $workspace->id,
        'usage_period_id' => $workspace->currentUsagePeriod()->id,
        'source_type' => CreditTransactionType::NodeRun,
        'source_id' => (string) str()->uuid(),
        'credits' => $credits,
        'revenue_usd' => $revenue,
    ]);
}

it('rolls the ledgers up into daily facts and reports revenue, cost and margin', function () {
    $profitable = economicsWorkspace('Profitable');
    $leaky = economicsWorkspace('Leaky');

    charge($profitable, 1000, 10.0);
    AiCallEvent::factory()->forWorkspace($profitable->id)->costing(4.0)->create(['provider' => 'openai', 'model' => 'gpt-x', 'feature' => 'agent_chat']);
    charge($leaky, 100, 1.0);
    AiCallEvent::factory()->forWorkspace($leaky->id)->costing(3.0)->create(['provider' => 'anthropic', 'model' => 'claude-x', 'feature' => 'workflow_node']);
    AiCallEvent::factory()->forWorkspace($leaky->id)->failed()->create(['provider' => 'anthropic', 'model' => 'claude-x', 'feature' => 'workflow_node']);
    AiCallEvent::factory()->forWorkspace($leaky->id)->costing(9.0)->create(['is_byok' => true, 'provider' => 'byok-1', 'model' => 'claude-x']);

    app(FactRollup::class)->run(1);
    $today = CarbonImmutable::today();
    $economics = app(UnitEconomics::class);
    $overview = $economics->overview($today, $today);

    expect($overview)->toMatchArray([
        'revenue_usd' => 11.0,
        'provider_cost_usd' => 7.0,
        'gross_margin_usd' => 4.0,
        'active_workspaces' => 2,
        'ai_calls' => 4,
        'failure_rate' => 0.25,
    ]);

    $losses = $economics->customers($today, $today);

    expect($losses)->toHaveCount(1)
        ->and($losses[0])->toMatchArray(['name' => 'Leaky', 'gross_margin_usd' => -2.0]);

    $models = collect($economics->byModel($today, $today))->keyBy(fn ($row) => "{$row['provider']}:{$row['model']}");

    expect($models['anthropic:claude-x']['failure_rate'])->toBe(0.5)
        ->and($models['openai:gpt-x']['provider_cost_usd'])->toBe(4.0)
        ->and($models['byok-1:claude-x']['provider_cost_usd'])->toBe(0.0);

    expect(collect($economics->byFeature($today, $today))->pluck('provider_cost_usd', 'feature')->all())
        ->toMatchArray(['agent_chat' => 4.0, 'workflow_node' => 3.0]);
});

it('rebuilds a day whole, so running the rollup twice changes nothing', function () {
    $workspace = economicsWorkspace('Acme');
    charge($workspace, 10, 0.1);

    app(FactRollup::class)->run(1);
    app(FactRollup::class)->run(1);

    expect(DB::table('fact_workspace_daily')->count())->toBe(1);
});

it('shows one customer\'s profit and loss by month', function () {
    $workspace = economicsWorkspace('Acme');
    charge($workspace, 10, 5.0);
    AiCallEvent::factory()->forWorkspace($workspace->id)->costing(2.0)->create();
    app(FactRollup::class)->run(1);

    $pnl = app(UnitEconomics::class)->customerPnl($workspace->id, 1);

    expect($pnl['months'])->toHaveCount(1)
        ->and($pnl['months'][0])->toMatchArray(['revenue_usd' => 5.0, 'provider_cost_usd' => 2.0, 'gross_margin_usd' => 3.0])
        ->and($pnl['models'])->toHaveCount(1);
});
