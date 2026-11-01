<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditGrantSource;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Billing\CreditGrant;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditWallet;
use App\Services\Workspaces\WorkspaceService;

function walletWorkspace(int $freeCredits = 100): Workspace
{
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => $freeCredits]);
    config(['billing.default_plan' => 'free']);

    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
}

it('spends the period allowance before carry-over credits and paid top-ups last', function () {
    $workspace = walletWorkspace(freeCredits: 100);
    $wallet = app(CreditWallet::class);
    $topup = $wallet->grant($workspace, CreditGrantSource::Topup, 1000);
    $promo = $wallet->grant($workspace, CreditGrantSource::Promo, 50, ['expires_at' => now()->addYear()]);

    $transaction = app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 'run-1', 150);

    $allowance = CreditGrant::query()->where('workspace_id', $workspace->id)->where('source', CreditGrantSource::Free)->sole();
    expect($allowance->remaining)->toBe(0)
        ->and($promo->fresh()->remaining)->toBe(0)
        ->and($topup->fresh()->remaining)->toBe(1000)
        ->and($transaction->topup_credits)->toBe(50)
        ->and($transaction->allocations()->pluck('credits', 'credit_grant_id')->all())
        ->toEqual([$allowance->id => 100, $promo->id => 50]);
});

it('keeps the top-up mirror and the balance in step with the grants', function () {
    $workspace = walletWorkspace(freeCredits: 100);
    app(CreditWallet::class)->grant($workspace, CreditGrantSource::Topup, 1000);

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 'run-1', 150);

    expect($workspace->fresh()->topup_credits)->toBe(950)
        ->and($workspace->fresh()->availableCredits())->toBe(950);
});

it('grants once per idempotency key', function () {
    $workspace = walletWorkspace();
    $wallet = app(CreditWallet::class);

    $first = $wallet->grant($workspace, CreditGrantSource::Topup, 500, ['idempotency_key' => 'pack:1']);
    $second = $wallet->grant($workspace, CreditGrantSource::Topup, 500, ['idempotency_key' => 'pack:1']);

    expect($second->id)->toBe($first->id)
        ->and($workspace->fresh()->topup_credits)->toBe(500);
});

it('skips an expired grant and records the credits that lapsed', function () {
    $workspace = walletWorkspace(freeCredits: 0);
    $promo = app(CreditWallet::class)->grant($workspace, CreditGrantSource::Promo, 40, ['expires_at' => now()->addDay()]);

    $this->travel(2)->days();
    $lapsed = app(CreditWallet::class)->expireDue();

    expect($lapsed)->toBe(40)
        ->and($promo->fresh()->expired_at)->not->toBeNull()
        ->and($workspace->fresh()->topup_credits)->toBe(0)
        ->and($workspace->fresh()->availableCredits())->toBe(0);
});

it('turns an overdraft into debt that the next grant pays first', function () {
    $workspace = walletWorkspace(freeCredits: 10);

    $transaction = app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 'run-1', 25, allowOverdraft: true);
    $grant = app(CreditWallet::class)->grant($workspace, CreditGrantSource::Topup, 100);

    expect($transaction->debt_credits)->toBe(15)
        ->and($grant->remaining)->toBe(85)
        ->and($workspace->fresh()->credit_debt)->toBe(0)
        ->and($workspace->fresh()->availableCredits())->toBe(85);
});

it('revokes what is left of a grant and turns the spent part into debt', function () {
    $workspace = walletWorkspace(freeCredits: 0);
    $wallet = app(CreditWallet::class);
    $pack = $wallet->grant($workspace, CreditGrantSource::Topup, 100);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 'run-1', 30);

    $revoked = $wallet->revoke($pack, 'Payment refunded', spentBecomesDebt: true);

    expect($revoked)->toBe(70)
        ->and($pack->fresh()->revoked_at)->not->toBeNull()
        ->and($workspace->fresh()->credit_debt)->toBe(30)
        ->and($workspace->fresh()->availableCredits())->toBe(0);
});

it('absorbs a direct write to the top-up column as a migration grant', function () {
    $workspace = walletWorkspace(freeCredits: 0);
    $workspace->increment('topup_credits', 40);

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 'run-1', 10);

    expect(CreditGrant::query()->where('workspace_id', $workspace->id)->where('source', CreditGrantSource::Migration)->sole()->remaining)->toBe(30)
        ->and($workspace->fresh()->topup_credits)->toBe(30);
});
