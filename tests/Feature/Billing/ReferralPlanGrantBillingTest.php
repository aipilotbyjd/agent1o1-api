<?php

use App\Actions\Billing\ActivatePlanGrantAction;
use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

it('keeps the more generous grant when a lesser one is granted later', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $pro = Plan::factory()->create(['credits_monthly' => 25000]);
    $starter = Plan::factory()->create(['credits_monthly' => 5000]);

    PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->active()->create(['granted_at' => now()->subMonth()]);
    PlanGrant::factory()->forWorkspace($workspace)->forPlan($starter)->active()->create([
        'source' => PlanGrantSource::Referral,
        'expires_at' => now()->addMonth(),
    ]);

    expect($workspace->activePlanGrant()->plan_id)->toBe($pro->id)
        ->and($workspace->currentPlan()->id)->toBe($pro->id);
});

it('expires lapsed fixed-term grants and shrinks the usage period back', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $pro = Plan::factory()->create(['credits_monthly' => 25000]);

    $this->travelTo(now()->startOfMonth()->addDays(2));

    $grant = PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->create([
        'source' => PlanGrantSource::Referral,
        'expires_at' => now()->addDays(5),
    ]);
    app(ActivatePlanGrantAction::class)->execute($grant);

    expect($workspace->currentUsagePeriod()->credits_limit)->toBe(25000);

    $this->travel(6)->days();
    $this->artisan('billing:expire-plan-grants')->assertSuccessful();

    expect($grant->fresh()->status)->toBe(PlanGrantStatus::Expired)
        ->and($workspace->currentUsagePeriod()->fresh()->credits_limit)->toBe(100);

    // Processed once: a second run finds nothing to do.
    $this->artisan('billing:expire-plan-grants')->expectsOutput('Expired 0 plan grant(s).')->assertSuccessful();
});
