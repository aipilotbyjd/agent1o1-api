<?php

use App\Enums\Auth\AuthEvent;
use App\Enums\Billing\ClaimDecision;
use App\Models\Auth\AuthEventLog;
use App\Models\Billing\FreeCreditClaim;
use App\Models\Billing\Plan;
use App\Models\Referrals\ReferralBlockedDomain;
use App\Models\User;
use App\Services\Billing\BillingSettings;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Auth\Events\Verified;

beforeEach(function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
});

it('gives the free credits to the owner\'s first workspace only', function () {
    $owner = User::factory()->create();
    $first = app(WorkspaceService::class)->create($owner, ['name' => 'First']);

    $second = app(WorkspaceService::class)->create($owner, ['name' => 'Second']);

    expect($first->availableCredits())->toBe(100)
        ->and($second->availableCredits())->toBe(0)
        ->and($owner->fresh()->free_workspace_id)->toBe($first->id);
});

it('does not hand the free credits to a new workspace after the first is deleted', function () {
    $owner = User::factory()->create();
    $first = app(WorkspaceService::class)->create($owner, ['name' => 'First']);
    $first->delete();

    $replacement = app(WorkspaceService::class)->create($owner, ['name' => 'Replacement']);

    expect($replacement->availableCredits())->toBe(0);
});

it('gives every free workspace its credits when the one-per-owner rule is off', function () {
    app(BillingSettings::class)->set('free_credits.one_per_owner', false);
    $owner = User::factory()->create();
    app(WorkspaceService::class)->create($owner, ['name' => 'First']);

    $second = app(WorkspaceService::class)->create($owner, ['name' => 'Second']);

    expect($second->availableCredits())->toBe(100);
});

it('withholds the free credits until the owner verifies their email', function () {
    $owner = User::factory()->unverified()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    expect($workspace->availableCredits())->toBe(0);

    $owner->markEmailAsVerified();
    event(new Verified($owner));

    expect($workspace->fresh()->availableCredits())->toBe(100);
});

it('refuses the free credits to a second account on the same Gmail mailbox', function () {
    $original = User::factory()->create(['email' => 'jane.doe@gmail.com']);
    app(WorkspaceService::class)->create($original, ['name' => 'Original'])->availableCredits();
    $alias = User::factory()->create(['email' => 'janedoe+free@googlemail.com']);

    $workspace = app(WorkspaceService::class)->create($alias, ['name' => 'Alias']);

    expect($workspace->availableCredits())->toBe(0);
    $this->assertDatabaseHas('free_credit_claims', ['workspace_id' => $workspace->id, 'decision' => ClaimDecision::Denied->value]);
});

it('refuses the free credits to a disposable email domain', function () {
    ReferralBlockedDomain::query()->create(['domain' => 'mailinator.com']);
    $owner = User::factory()->create(['email' => 'someone@mailinator.com']);

    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    expect($workspace->availableCredits())->toBe(0);
});

it('stops granting after too many free accounts from one network in a day', function () {
    $signup = function (string $ip): User {
        $user = User::factory()->create();
        AuthEventLog::query()->create(['user_id' => $user->id, 'email' => $user->email, 'event' => AuthEvent::Registered, 'ip_address' => $ip]);

        return $user;
    };
    foreach (['203.0.113.10', '203.0.113.11', '203.0.113.12'] as $ip) {
        app(WorkspaceService::class)->create($signup($ip), ['name' => 'Acme'])->availableCredits();
    }

    $fourth = app(WorkspaceService::class)->create($signup('203.0.113.99'), ['name' => 'Acme']);

    expect($fourth->availableCredits())->toBe(0);
});

it('grants the credits but records the denial in shadow mode', function () {
    app(BillingSettings::class)->set('abuse.enforcement', 'shadow');
    $original = User::factory()->create(['email' => 'jane.doe@gmail.com']);
    app(WorkspaceService::class)->create($original, ['name' => 'Original'])->availableCredits();
    $alias = User::factory()->create(['email' => 'j.a.n.e.doe@gmail.com']);

    $workspace = app(WorkspaceService::class)->create($alias, ['name' => 'Alias']);

    expect($workspace->availableCredits())->toBe(100)
        ->and(FreeCreditClaim::query()->where('workspace_id', $workspace->id)->sole()->decision)->toBe(ClaimDecision::ShadowDenied);
});
