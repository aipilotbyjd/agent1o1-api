<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditGrantSource;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Billing\CreditGrant;
use App\Models\Billing\CreditPack;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Services\Billing\CreditRevenue;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    config([
        'billing.default_plan' => 'free',
        'billing.credit_value_usd' => 0.005,
        'billing.overage.credit_value_usd' => 0.0065,
        'billing.margin_floor.lifetime_months' => 36,
    ]);

    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100, 'price_monthly' => 0]);
    $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
});

it('earns nothing per allowance credit on the free plan', function () {
    expect(app(CreditRevenue::class)->planUsdPerCredit($this->workspace))->toBe(0.0);
});

it('values allowance credits from a lifetime purchase at the price spread over its months', function () {
    $plan = Plan::factory()->create(['slug' => 'pro', 'credits_monthly' => 1_000, 'price_lifetime' => 36_000]);
    PlanGrant::factory()->forWorkspace($this->workspace)->forPlan($plan)->active()->create();

    // $360 / (1,000 credits × 36 months) = $0.01 a credit.
    expect(app(CreditRevenue::class)->planUsdPerCredit($this->workspace->fresh()))->toEqualWithDelta(0.01, 0.0000001);
});

it('adds up a charge from what each funding source earns per credit', function () {
    $plan = Plan::factory()->create(['slug' => 'pro', 'credits_monthly' => 1_000, 'price_lifetime' => 36_000]);
    PlanGrant::factory()->forWorkspace($this->workspace)->forPlan($plan)->active()->create();
    $pack = CreditPack::factory()->forWorkspace($this->workspace)->create(['credits_amount' => 1_000, 'price_cents' => 900]);

    $allocations = [
        ['grant' => new CreditGrant(['source' => CreditGrantSource::Plan]), 'credits' => 100],
        ['grant' => new CreditGrant(['source' => CreditGrantSource::Free]), 'credits' => 50],
        ['grant' => new CreditGrant(['source' => CreditGrantSource::Topup, 'reference_type' => $pack->getMorphClass(), 'reference_id' => $pack->id]), 'credits' => 100],
    ];

    // 100 × $0.01 + 50 × $0 + 100 × $0.009 + 10 overage × $0.0065.
    expect(app(CreditRevenue::class)->forCharge($this->workspace->fresh(), $allocations, 10))->toEqualWithDelta(1.965, 0.000001);
});

it('stores what each charge earned on the ledger', function () {
    $transaction = app(DeductCreditsAction::class)->execute($this->workspace, CreditTransactionType::NodeRun, 'n1', 10);

    expect($transaction->fresh()->revenue_usd)->toBe(0.0);
});
