<?php

use App\Enums\Billing\ClaimDecision;
use App\Models\Billing\Subscription;
use App\Models\Billing\TrialClaim;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\Policies\TrialCreditPolicy;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Str;

function trialWorkspace(?User $owner = null): Workspace
{
    return app(WorkspaceService::class)->create($owner ?? User::factory()->create(), ['name' => 'Acme']);
}

function trialSubscription(Workspace $workspace): Subscription
{
    return Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.Str::random(10),
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_trial',
        'trial_ends_at' => now()->addDays(14),
    ]);
}

it('offers a trial to a person who never had one', function () {
    $workspace = trialWorkspace();

    expect(app(TrialCreditPolicy::class)->trialDeniedReason($workspace, $workspace->owner))->toBeNull();
});

it('refuses a second trial to the same person in another workspace', function () {
    $owner = User::factory()->create();
    $first = trialWorkspace($owner);
    app(TrialCreditPolicy::class)->checkCard($first, trialSubscription($first), 'fp_card_a', 'credit');
    $second = trialWorkspace($owner);

    expect(app(TrialCreditPolicy::class)->trialDeniedReason($second, $owner))->toBe('You have already used a free trial.');
});

it('refuses a second trial to a workspace that already had one', function () {
    $workspace = trialWorkspace();
    trialSubscription($workspace);

    expect(app(TrialCreditPolicy::class)->trialDeniedReason($workspace, $workspace->owner))->toBe('This workspace already had a trial.');
});

it('ends the trial when the card was already used for one', function () {
    $first = trialWorkspace();
    app(TrialCreditPolicy::class)->checkCard($first, trialSubscription($first), 'fp_shared', 'credit');
    $second = trialWorkspace();

    $reason = app(TrialCreditPolicy::class)->checkCard($second, trialSubscription($second), 'fp_shared', 'credit');

    expect($reason)->toBe('This card was already used for a trial.');
    $this->assertDatabaseHas('trial_claims', ['workspace_id' => $second->id, 'decision' => ClaimDecision::Denied->value]);
});

it('ends the trial for a prepaid card', function () {
    $workspace = trialWorkspace();

    $reason = app(TrialCreditPolicy::class)->checkCard($workspace, trialSubscription($workspace), 'fp_prepaid', 'prepaid');

    expect($reason)->toBe('Trials are not available with prepaid cards.');
});

it('lets a reused card keep its trial in shadow mode but records the denial', function () {
    app(BillingSettings::class)->set('abuse.enforcement', 'shadow');
    $first = trialWorkspace();
    app(TrialCreditPolicy::class)->checkCard($first, trialSubscription($first), 'fp_shadow', 'credit');
    $second = trialWorkspace();

    $reason = app(TrialCreditPolicy::class)->checkCard($second, trialSubscription($second), 'fp_shadow', 'credit');

    expect($reason)->toBeNull()
        ->and(TrialClaim::query()->where('workspace_id', $second->id)->sole()->decision)->toBe(ClaimDecision::ShadowDenied);
});
