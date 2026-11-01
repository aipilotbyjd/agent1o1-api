<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\QueryException;

it('records a transaction and increments the current usage period', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $transaction = app(DeductCreditsAction::class)->execute(
        $workspace,
        CreditTransactionType::NodeRun,
        123,
        5,
        'Test charge',
    );

    expect($transaction->credits)->toBe(5);
    expect($transaction->source_type)->toBe(CreditTransactionType::NodeRun);
    expect($transaction->source_id)->toBe('123');

    $period = $workspace->currentUsagePeriod();
    expect($period->credits_used)->toBe(5);
});

it('accumulates across multiple charges in the same period', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 3);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::AgentStep, 2, 4);

    expect($workspace->currentUsagePeriod()->credits_used)->toBe(7);
    expect($workspace->creditTransactions()->count())->toBe(2);
});

it('throws once a charge would exceed the usage period credits_limit', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->currentUsagePeriod()->update(['credits_limit' => 10]);

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 8);

    expect(fn () => app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 2, 5))
        ->toThrow(InsufficientCreditsException::class);

    // The over-limit attempt never wrote a transaction or incremented usage.
    expect($workspace->currentUsagePeriod()->credits_used)->toBe(8);
    expect($workspace->creditTransactions()->count())->toBe(1);
});

it('is idempotent per source, so a replayed charge cannot double-bill', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $first = app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 42, 5);
    $second = app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 42, 5);

    expect($second->id)->toBe($first->id);
    expect($workspace->creditTransactions()->count())->toBe(1);
    expect($workspace->currentUsagePeriod()->credits_used)->toBe(5);
});

it('rejects a duplicate ledger row at the database level', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 7, 1);

    expect(fn () => CreditTransaction::query()->create([
        'workspace_id' => $workspace->id,
        'source_type' => CreditTransactionType::NodeRun,
        'source_id' => 7,
        'credits' => 1,
    ]))->toThrow(QueryException::class);
});

it('records which usage period a charge was counted against', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $transaction = app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 3);

    expect($transaction->usage_period_id)->toBe($workspace->currentUsagePeriod()->id);
});

it('draws on top-up credits only once the plan allowance is spent', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->currentUsagePeriod()->update(['credits_limit' => 10]);
    $workspace->increment('topup_credits', 20);

    // Fits inside the plan allowance: top-up untouched.
    $first = app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 8);
    expect($first->topup_credits)->toBe(0);
    expect($workspace->fresh()->topup_credits)->toBe(20);

    // Straddles the boundary: 2 from the plan, 6 from the pool.
    $second = app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 2, 8);
    expect($second->topup_credits)->toBe(6);
    expect($workspace->fresh()->topup_credits)->toBe(14);
    expect($workspace->fresh()->availableCredits())->toBe(14);
});

it('throws only when plan allowance and top-up credits together fall short', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->currentUsagePeriod()->update(['credits_limit' => 10]);
    $workspace->increment('topup_credits', 3);

    expect(fn () => app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 14))
        ->toThrow(InsufficientCreditsException::class);

    expect($workspace->creditTransactions()->count())->toBe(0);
    expect($workspace->fresh()->topup_credits)->toBe(3);
});

it('records already-performed work under overdraft instead of throwing', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->currentUsagePeriod()->update(['credits_limit' => 10]);
    $workspace->increment('topup_credits', 2);

    $transaction = app(DeductCreditsAction::class)->execute(
        $workspace,
        CreditTransactionType::NodeRun,
        1,
        20,
        'Expensive node',
        allowOverdraft: true,
    );

    // The compute was already spent, so the ledger tells the truth: the pool is
    // drained and the remainder overruns the period.
    expect($transaction->credits)->toBe(20);
    expect($transaction->topup_credits)->toBe(2);
    expect($workspace->fresh()->topup_credits)->toBe(0);
    expect($workspace->currentUsagePeriod()->credits_used)->toBe(20);

    // ...and the balance is floored at zero, so the next run is refused.
    expect($workspace->fresh()->availableCredits())->toBe(0);
});
