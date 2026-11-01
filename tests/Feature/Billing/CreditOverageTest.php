<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\Billing\CreditTransactionType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Notifications\Billing\CreditsExhaustedNotification;
use App\Notifications\Billing\OverageCapReachedNotification;
use App\Services\Billing\CreditGate;
use App\Services\Billing\CreditOverage;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

/**
 * Credit overage: Gumloop's "keep running past your monthly credits, billed
 * at $0.005 per credit", with a cap that can only ever be lowered.
 */
function workspaceOnOveragePlan(int $creditsMonthly = 10, bool $sellsOverage = true): Workspace
{
    Plan::factory()->create([
        'slug' => 'free',
        'credits_monthly' => $creditsMonthly,
        'features' => $sellsOverage ? ['credit_overage' => true] : [],
    ]);
    config(['billing.default_plan' => 'free']);

    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
}

/**
 * A trivial one-node published workflow: enough to produce a real billable
 * run without depending on `CreditGateTest`'s helper of the same shape.
 */
function overageWorkflowFor(Workspace $workspace): Workflow
{
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $workspace->owner);

    return $workflow->fresh();
}

function exhaustOveragePlanAllowance(Workspace $workspace): Workspace
{
    $period = $workspace->currentUsagePeriod();
    $period->forceFill(['credits_used' => $period->credits_limit])->save();

    return $workspace->fresh();
}

it('is unavailable on a plan that does not sell it', function () {
    $workspace = workspaceOnOveragePlan(sellsOverage: false);

    expect(app(CreditOverage::class)->isAvailableTo($workspace))->toBeFalse();
});

it('grants nothing until the workspace opts in', function () {
    $workspace = workspaceOnOveragePlan();

    expect(app(CreditOverage::class)->remainingFor($workspace))->toBe(0);
});

it('falls back to the estate default cap when the workspace set none', function () {
    config(['billing.overage.default_limit' => 5_000]);
    $workspace = workspaceOnOveragePlan();
    $workspace->forceFill(['credit_overage_enabled' => true])->save();

    expect(app(CreditOverage::class)->effectiveLimitFor($workspace))->toBe(5_000);
});

it('never lets a workspace cap exceed the estate ceiling', function () {
    config(['billing.overage.default_limit' => 1_000]);
    $workspace = workspaceOnOveragePlan();
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 9_999])->save();

    expect(app(CreditOverage::class)->effectiveLimitFor($workspace))->toBe(1_000);
});

it('reports an uncapped allowance when the estate ceiling is lifted', function () {
    config(['billing.overage.default_limit' => null]);
    $workspace = workspaceOnOveragePlan();
    $workspace->forceFill(['credit_overage_enabled' => true])->save();

    expect(app(CreditOverage::class)->remainingFor($workspace))->toBeNull();
});

it('stops granting overage when the plan stops selling it', function () {
    $workspace = workspaceOnOveragePlan(sellsOverage: false);
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 500])->save();

    // The opt-in is preserved, so upgrading restores it, but it entitles
    // nothing while the plan doesn't carry the feature.
    expect(app(CreditOverage::class)->remainingFor($workspace))->toBe(0)
        ->and($workspace->credit_overage_enabled)->toBeTrue();
});

it('lets a run start on overage once the allowance is spent', function () {
    $workspace = workspaceOnOveragePlan();
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 100])->save();
    $workspace = exhaustOveragePlanAllowance($workspace);

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace))->not->toThrow(InsufficientCreditsException::class);
});

it('refuses a run once the overage cap is spent too', function () {
    $workspace = workspaceOnOveragePlan();
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 5])->save();
    $workspace = exhaustOveragePlanAllowance($workspace);
    $workspace->currentUsagePeriod()->increment('overage_credits_used', 5);

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace->fresh()))
        ->toThrow(InsufficientCreditsException::class);
});

it('tells a workspace its cap is reached rather than that it is out of credits', function () {
    Notification::fake();
    $workspace = workspaceOnOveragePlan();
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 5])->save();
    $workspace = exhaustOveragePlanAllowance($workspace);
    $workspace->currentUsagePeriod()->increment('overage_credits_used', 5);

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace->fresh()))
        ->toThrow(InsufficientCreditsException::class);

    Notification::assertSentTo(
        $workspace->owner,
        OverageCapReachedNotification::class,
        fn (OverageCapReachedNotification $n): bool => $n->data['overage_credits_limit'] === 5,
    );
    Notification::assertNotSentTo($workspace->owner, CreditsExhaustedNotification::class);
});

it('charges the plan allowance before it touches overage', function () {
    $workspace = workspaceOnOveragePlan(creditsMonthly: 10);
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 100])->save();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 4);

    $transaction = CreditTransaction::query()->latest('id')->firstOrFail();

    expect($transaction->overage_credits)->toBe(0)
        ->and($workspace->currentUsagePeriod()->overage_credits_used)->toBe(0);
});

it('spends top-up credits before it touches overage', function () {
    $workspace = workspaceOnOveragePlan(creditsMonthly: 10);
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 100])->save();
    $workspace->increment('topup_credits', 5);
    $workspace = exhaustOveragePlanAllowance($workspace);

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 8);

    $transaction = CreditTransaction::query()->latest('id')->firstOrFail();

    expect($transaction->topup_credits)->toBe(5)
        ->and($transaction->overage_credits)->toBe(3)
        ->and($workspace->fresh()->topup_credits)->toBe(0)
        ->and($workspace->currentUsagePeriod()->overage_credits_used)->toBe(3);
});

it('records only the part of a charge overage actually covered', function () {
    $workspace = workspaceOnOveragePlan(creditsMonthly: 10);
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 4])->save();
    $workspace = exhaustOveragePlanAllowance($workspace);

    // 10 credits of work against a 4-credit cap: the workspace agreed to be
    // billed for 4, so the other 6 overdraft unbilled rather than sneaking
    // past the cap.
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 10, allowOverdraft: true);

    $transaction = CreditTransaction::query()->latest('id')->firstOrFail();

    expect($transaction->credits)->toBe(10)
        ->and($transaction->overage_credits)->toBe(4)
        ->and($workspace->currentUsagePeriod()->overage_credits_used)->toBe(4);
});

it('refuses a non-overdraft charge that the overage cap cannot cover', function () {
    $workspace = workspaceOnOveragePlan(creditsMonthly: 10);
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 2])->save();
    $workspace = exhaustOveragePlanAllowance($workspace);

    expect(fn () => app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 5))
        ->toThrow(InsufficientCreditsException::class);
});

it('bills a real workflow run to overage once the allowance is gone', function () {
    $workspace = workspaceOnOveragePlan(creditsMonthly: 1);
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 50])->save();
    $workflow = overageWorkflowFor($workspace);

    app(StartWorkflowRunAction::class)->execute($workflow);
    app(StartWorkflowRunAction::class)->execute($workflow);

    expect($workspace->currentUsagePeriod()->overage_credits_used)->toBeGreaterThan(0);
});

it('reports the overage allowance on the billing overview', function () {
    $workspace = workspaceOnOveragePlan();
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 250])->save();

    Passport::actingAs($workspace->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing");

    $response->assertOk();
    expect($response->json('data.overage'))->toMatchArray([
        'available' => true,
        'enabled' => true,
        'effective_limit' => 250,
        'credits_used' => 0,
        'credits_remaining' => 250,
    ]);
});

it('reads the overage settings back over the API', function () {
    config(['billing.overage.default_limit' => 1_000]);
    $workspace = workspaceOnOveragePlan();

    Passport::actingAs($workspace->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/overage");

    $response->assertOk();
    expect($response->json('data.overage'))->toMatchArray([
        'available' => true,
        'enabled' => false,
        'limit' => null,
        'effective_limit' => 1_000,
        'maximum_limit' => 1_000,
    ]);
});

it('enables overage and sets a cap over the API', function () {
    config(['billing.overage.default_limit' => 1_000]);
    $workspace = workspaceOnOveragePlan();

    Passport::actingAs($workspace->owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/billing/overage", [
        'enabled' => true,
        'limit' => 400,
    ])->assertOk();

    expect($workspace->fresh()->credit_overage_enabled)->toBeTrue()
        ->and($workspace->fresh()->credit_overage_limit)->toBe(400);
});

it('refuses a cap above the estate ceiling', function () {
    config(['billing.overage.default_limit' => 1_000]);
    $workspace = workspaceOnOveragePlan();

    Passport::actingAs($workspace->owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/billing/overage", [
        'enabled' => true,
        'limit' => 5_000,
    ])->assertStatus(422)->assertJsonValidationErrors('limit');
});

it('clears a workspace cap back to the estate default', function () {
    config(['billing.overage.default_limit' => 1_000]);
    $workspace = workspaceOnOveragePlan();
    $workspace->forceFill(['credit_overage_enabled' => true, 'credit_overage_limit' => 400])->save();

    Passport::actingAs($workspace->owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/billing/overage", [
        'enabled' => true,
        'limit' => null,
    ])->assertOk();

    expect($workspace->fresh()->credit_overage_limit)->toBeNull();
});

it('refuses to enable overage on a plan that does not sell it', function () {
    $workspace = workspaceOnOveragePlan(sellsOverage: false);

    Passport::actingAs($workspace->owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/billing/overage", ['enabled' => true])
        ->assertStatus(403);

    expect($workspace->fresh()->credit_overage_enabled)->toBeFalse();
});

it('still lets a workspace switch overage off on a plan that does not sell it', function () {
    $workspace = workspaceOnOveragePlan(sellsOverage: false);
    $workspace->forceFill(['credit_overage_enabled' => true])->save();

    Passport::actingAs($workspace->owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/billing/overage", ['enabled' => false])
        ->assertOk();

    expect($workspace->fresh()->credit_overage_enabled)->toBeFalse();
});
