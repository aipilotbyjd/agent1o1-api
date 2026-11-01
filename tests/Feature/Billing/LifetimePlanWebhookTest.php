<?php

use App\Enums\Billing\PlanGrantStatus;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;

/**
 * @return array{0: Workspace, 1: Plan}
 */
function workspaceAndLifetimePlan(string $customerId): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->forceFill(['stripe_id' => $customerId])->save();

    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);

    $plan = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    return [$workspace, $plan];
}

/**
 * @return array<string, mixed>
 */
function planGrantCheckoutPayload(string $eventId, string $customerId, string $grantId, string $paymentIntentId): array
{
    return [
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_'.$grantId,
                'customer' => $customerId,
                'payment_intent' => $paymentIntentId,
                'metadata' => [
                    'type' => 'plan_grant',
                    'plan_grant_id' => $grantId,
                ],
            ],
        ],
    ];
}

it('activates a lifetime grant via the checkout.session.completed webhook', function () {
    [$workspace, $plan] = workspaceAndLifetimePlan('cus_lifetime_1');
    $grant = PlanGrant::factory()->forWorkspace($workspace)->forPlan($plan)->create();

    $this->postJson('/api/stripe/webhook', planGrantCheckoutPayload('evt_lifetime_1', 'cus_lifetime_1', $grant->id, 'pi_lifetime_1'))
        ->assertOk();

    $fresh = $grant->fresh();

    expect($fresh->status)->toBe(PlanGrantStatus::Active)
        ->and($fresh->stripe_payment_intent_id)->toBe('pi_lifetime_1')
        ->and($workspace->currentPlan()->id)->toBe($plan->id)
        ->and($workspace->currentUsagePeriod()->fresh()->credits_limit)->toBe(25000);
});

it('does not double-apply a redelivered lifetime checkout event', function () {
    [$workspace, $plan] = workspaceAndLifetimePlan('cus_lifetime_2');
    $grant = PlanGrant::factory()->forWorkspace($workspace)->forPlan($plan)->create();

    $payload = planGrantCheckoutPayload('evt_lifetime_2', 'cus_lifetime_2', $grant->id, 'pi_lifetime_2');

    $this->postJson('/api/stripe/webhook', $payload)->assertOk();
    $grantedAt = $grant->fresh()->granted_at;

    $this->postJson('/api/stripe/webhook', $payload)->assertOk();

    expect($grant->fresh()->granted_at->timestamp)->toBe($grantedAt->timestamp)
        ->and(PlanGrant::query()->count())->toBe(1);
});

it('ignores a plan_grant session whose grant no longer exists', function () {
    [$workspace] = workspaceAndLifetimePlan('cus_lifetime_3');

    $this->postJson('/api/stripe/webhook', planGrantCheckoutPayload('evt_lifetime_3', 'cus_lifetime_3', 99999, 'pi_lifetime_3'))
        ->assertOk();
});

it('still ignores checkout sessions carrying neither pack nor grant metadata', function () {
    [$workspace] = workspaceAndLifetimePlan('cus_lifetime_4');

    $this->postJson('/api/stripe/webhook', [
        'id' => 'evt_lifetime_4',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_none', 'customer' => 'cus_lifetime_4', 'metadata' => []]],
    ])->assertOk();
});

it('revokes a lifetime grant when its charge is refunded', function () {
    [$workspace, $plan] = workspaceAndLifetimePlan('cus_lifetime_5');
    $grant = PlanGrant::factory()->forWorkspace($workspace)->forPlan($plan)->create();

    $this->postJson('/api/stripe/webhook', planGrantCheckoutPayload('evt_lifetime_5', 'cus_lifetime_5', $grant->id, 'pi_lifetime_5'))
        ->assertOk();

    expect($workspace->currentPlan()->id)->toBe($plan->id);

    // Without this the refunded customer would keep the plan forever: the
    // grant has no expiry and no subscription status to lapse.
    $this->postJson('/api/stripe/webhook', [
        'id' => 'evt_lifetime_refund',
        'type' => 'charge.refunded',
        'data' => ['object' => ['id' => 'ch_1', 'payment_intent' => 'pi_lifetime_5']],
    ])->assertOk();

    expect($grant->fresh()->status)->toBe(PlanGrantStatus::Revoked)
        ->and($workspace->currentPlan()->slug)->toBe('free')
        ->and($workspace->currentUsagePeriod()->fresh()->credits_limit)->toBe(100);
});

it('revokes a lifetime grant when its charge is disputed', function () {
    [$workspace, $plan] = workspaceAndLifetimePlan('cus_lifetime_6');
    $grant = PlanGrant::factory()->forWorkspace($workspace)->forPlan($plan)->create();

    $this->postJson('/api/stripe/webhook', planGrantCheckoutPayload('evt_lifetime_6', 'cus_lifetime_6', $grant->id, 'pi_lifetime_6'))
        ->assertOk();

    $this->postJson('/api/stripe/webhook', [
        'id' => 'evt_lifetime_dispute',
        'type' => 'charge.dispute.created',
        'data' => ['object' => ['id' => 'dp_1', 'payment_intent' => 'pi_lifetime_6']],
    ])->assertOk();

    expect($grant->fresh()->status)->toBe(PlanGrantStatus::Revoked);
});

it('ignores a refund for a payment intent that never granted a plan', function () {
    workspaceAndLifetimePlan('cus_lifetime_7');

    $this->postJson('/api/stripe/webhook', [
        'id' => 'evt_lifetime_refund_orphan',
        'type' => 'charge.refunded',
        'data' => ['object' => ['id' => 'ch_2', 'payment_intent' => 'pi_unknown']],
    ])->assertOk();
});
