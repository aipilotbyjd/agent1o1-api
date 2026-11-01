<?php

use App\Models\Billing\CreditPackDefinition;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanPriceHistory;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/*
 * What the Super Admin pricing manager changes for ordinary customers: the
 * public plan list, the pack list, and the seeder no longer overwriting
 * admin edits.
 */

it('keeps a retired Stripe price mapped to its plan so a price change cannot orphan subscribers', function () {
    $plan = Plan::factory()->create(['stripe_price_id_monthly' => 'price_new']);
    PlanPriceHistory::query()->create(['plan_id' => $plan->id, 'interval' => 'monthly', 'stripe_price_id' => 'price_retired', 'amount_cents' => 1900, 'retired_at' => now()]);

    expect(Plan::findByStripePriceId('price_retired')?->id)->toBe($plan->id)
        ->and(Plan::findByStripePriceId('price_new')?->id)->toBe($plan->id);
});

it('hides hidden and archived plans from the customer plan list', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Plan::factory()->create(['slug' => 'visible', 'sort_order' => 1]);
    Plan::factory()->create(['slug' => 'secret', 'is_public' => false, 'sort_order' => 2]);
    Plan::factory()->create(['slug' => 'old', 'archived_at' => now(), 'is_active' => false, 'sort_order' => 3]);
    Plan::factory()->create(['slug' => 'badged', 'badge' => 'Most popular', 'sort_order' => 4]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/plans")->assertOk();

    expect(collect($response->json('data.plans'))->pluck('slug')->all())->toBe(['visible', 'badged'])
        ->and($response->json('data.plans.1.badge'))->toBe('Most popular');
});

it('leaves a plan alone when the seeder runs again after an admin edited it', function () {
    test()->syncSetup('plans');

    Plan::query()->where('slug', 'starter')->update(['price_monthly' => 3500, 'credits_monthly' => 9000, 'is_active' => false]);

    test()->syncSetup('plans');

    $starter = Plan::query()->where('slug', 'starter')->sole();

    expect($starter->price_monthly)->toBe(3500)
        ->and($starter->credits_monthly)->toBe(9000)
        ->and($starter->is_active)->toBeFalse()
        ->and(Plan::query()->count())->toBe(3);
});

it('lists admin-defined packs to customers, with badges, instead of config', function () {
    config(['billing.packs' => ['small' => ['label' => 'Config pack', 'credits' => 1000, 'price_cents' => 900]]]);
    CreditPackDefinition::factory()->create(['key' => 'db-pack', 'label' => 'DB pack', 'badge' => 'Best value']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/credit-packs")->assertOk();

    expect(collect($response->json('data.packs'))->pluck('key')->all())->toBe(['db-pack'])
        ->and($response->json('data.packs.0.badge'))->toBe('Best value');
});
