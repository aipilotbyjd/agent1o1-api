<?php

use App\Actions\Billing\ActivateCreditPackAction;
use App\Actions\Billing\OpenUsagePeriodForSubscriptionAction;
use App\Enums\Billing\CreditPackStatus;
use App\Models\Billing\CreditPack;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForCreditPack(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('lists the pack catalog as unavailable when the plan lacks the feature', function () {
    [$workspace, $owner] = ownerWorkspaceForCreditPack();
    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/credit-packs");

    $response->assertOk();
    expect($response->json('data.packs.0.available'))->toBeFalse();
});

it('marks the pack catalog available when the plan has the credit_packs feature', function () {
    [$workspace, $owner] = ownerWorkspaceForCreditPack();
    $plan = Plan::factory()->create(['features' => ['credit_packs' => true]]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_pack_1',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
    ]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/credit-packs");

    $response->assertOk();
    expect($response->json('data.packs.0.available'))->toBeTrue();
});

it('rejects checkout when the plan lacks the credit_packs feature', function () {
    [$workspace, $owner] = ownerWorkspaceForCreditPack();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/credit-packs/checkout", [
        'pack_key' => 'small',
    ]);

    $response->assertStatus(403);
});

it('lists purchased credit packs newest first', function () {
    [$workspace, $owner] = ownerWorkspaceForCreditPack();
    CreditPack::factory()->forWorkspace($workspace)->create(['pack_key' => 'small']);
    CreditPack::factory()->forWorkspace($workspace)->active()->create(['pack_key' => 'medium']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/credit-packs/purchased");

    $response->assertOk();
    expect($response->json('data.credit_packs'))->toHaveCount(2);
});

it('activates a pending pack into the non-expiring top-up balance', function () {
    [$workspace] = ownerWorkspaceForCreditPack();
    $workspace->currentUsagePeriod()->update(['credits_limit' => 1000]);

    $pack = CreditPack::factory()->forWorkspace($workspace)->create(['credits_amount' => 500]);

    app(ActivateCreditPackAction::class)->execute($pack);

    expect($pack->fresh()->status)->toBe(CreditPackStatus::Active);
    expect($workspace->fresh()->topup_credits)->toBe(500);

    // The period's plan allowance is untouched: pack credits are not part of it.
    expect($workspace->currentUsagePeriod()->fresh()->credits_limit)->toBe(1000);
});

it('credits a pack even when the usage period is unlimited', function () {
    [$workspace] = ownerWorkspaceForCreditPack();
    $workspace->currentUsagePeriod()->update(['credits_limit' => null]);

    $pack = CreditPack::factory()->forWorkspace($workspace)->create(['credits_amount' => 500]);

    app(ActivateCreditPackAction::class)->execute($pack);

    // Previously the increment was skipped entirely here, so the customer paid
    // and received nothing.
    expect($workspace->fresh()->topup_credits)->toBe(500);
});

it('activating an already-active pack does not double-credit the top-up balance', function () {
    [$workspace] = ownerWorkspaceForCreditPack();
    $pack = CreditPack::factory()->forWorkspace($workspace)->active()->create(['credits_amount' => 500]);

    app(ActivateCreditPackAction::class)->execute($pack);

    expect($workspace->fresh()->topup_credits)->toBe(0);
});

it('keeps purchased credits when a subscription webhook resets the period limit', function () {
    [$workspace] = ownerWorkspaceForCreditPack();
    $plan = Plan::factory()->create(['credits_monthly' => 1000]);

    app(ActivateCreditPackAction::class)->execute(
        CreditPack::factory()->forWorkspace($workspace)->create(['credits_amount' => 500]),
    );

    // A redelivered customer.subscription.updated used to wipe pack credits by
    // resetting credits_limit to the plan's monthly allowance.
    app(OpenUsagePeriodForSubscriptionAction::class)->execute($workspace, $plan, null);

    expect($workspace->fresh()->topup_credits)->toBe(500);
    expect($workspace->currentUsagePeriod()->fresh()->credits_limit)->toBe(1000);
});

it('keeps purchased credits when the usage period rolls over to the next month', function () {
    [$workspace] = ownerWorkspaceForCreditPack();

    app(ActivateCreditPackAction::class)->execute(
        CreditPack::factory()->forWorkspace($workspace)->create(['credits_amount' => 500]),
    );

    $this->travel(1)->months();

    expect($workspace->fresh()->topup_credits)->toBe(500);
});

it('activates a credit pack via the checkout.session.completed webhook', function () {
    [$workspace] = ownerWorkspaceForCreditPack();
    $workspace->forceFill(['stripe_id' => 'cus_test_pack'])->save();
    $workspace->currentUsagePeriod()->update(['credits_limit' => 1000]);

    $pack = CreditPack::factory()->forWorkspace($workspace)->create(['credits_amount' => 250]);

    $payload = [
        'id' => 'evt_test_checkout_1',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_1',
                'customer' => 'cus_test_pack',
                'payment_intent' => 'pi_test_1',
                'metadata' => [
                    'type' => 'credit_pack',
                    'credit_pack_id' => (string) $pack->id,
                ],
            ],
        ],
    ];

    $this->postJson('/api/stripe/webhook', $payload)->assertOk();

    $fresh = $pack->fresh();
    expect($fresh->status)->toBe(CreditPackStatus::Active);
    expect($fresh->stripe_payment_intent_id)->toBe('pi_test_1');
    expect($workspace->fresh()->topup_credits)->toBe(250);
});

it('ignores checkout.session.completed sessions without credit_pack metadata', function () {
    [$workspace] = ownerWorkspaceForCreditPack();
    $workspace->forceFill(['stripe_id' => 'cus_test_other'])->save();

    $payload = [
        'id' => 'evt_test_checkout_2',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_2',
                'customer' => 'cus_test_other',
                'metadata' => [],
            ],
        ],
    ];

    $this->postJson('/api/stripe/webhook', $payload)->assertOk();
});

it('waits for the async payment before crediting a pack paid by a delayed method', function () {
    [$workspace] = ownerWorkspaceForCreditPack();
    $workspace->forceFill(['stripe_id' => 'cus_test_async'])->save();

    $pack = CreditPack::factory()->forWorkspace($workspace)->create(['credits_amount' => 250]);

    $session = [
        'id' => 'cs_test_async',
        'customer' => 'cus_test_async',
        'payment_intent' => 'pi_test_async',
        'metadata' => ['type' => 'credit_pack', 'credit_pack_id' => (string) $pack->id],
    ];

    $this->postJson('/api/stripe/webhook', [
        'id' => 'evt_test_async_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => $session + ['payment_status' => 'unpaid']],
    ])->assertOk();

    expect($pack->fresh()->status)->not->toBe(CreditPackStatus::Active)
        ->and($workspace->fresh()->topup_credits)->toBe(0);

    $this->postJson('/api/stripe/webhook', [
        'id' => 'evt_test_async_2',
        'type' => 'checkout.session.async_payment_succeeded',
        'data' => ['object' => $session + ['payment_status' => 'paid']],
    ])->assertOk();

    expect($pack->fresh()->status)->toBe(CreditPackStatus::Active)
        ->and($workspace->fresh()->topup_credits)->toBe(250);
});
