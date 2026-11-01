<?php

use App\Enums\Billing\BillingInterval;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Billing\CreditPack;
use App\Models\Billing\CreditPackDefinition;
use App\Models\Billing\Plan;
use App\Models\Billing\PricingChangeLog;
use App\Models\User;
use App\Services\Billing\PackCatalog;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->finance = superAdmin(SuperAdminRole::Finance);
    $this->headers = superAdminHeaders($this->finance);

    config(['billing.packs' => [
        'small' => ['label' => '1,000 credits', 'credits' => 1000, 'price_cents' => 900],
        'large' => ['label' => '20,000 credits', 'credits' => 20000, 'price_cents' => 12900],
    ]]);
});

it('lists the config packs, flagged as such, until an admin defines one', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('pricing/packs'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.key', 'small')
        ->assertJsonPath('data.0.source', 'config');

    expect(app(PackCatalog::class)->sellable())->toHaveKeys(['small', 'large']);
});

it('imports the config packs on the first write, so a new pack does not hide the old ones', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('pricing/packs'), stepUp($this->finance, [
        'key' => 'jumbo', 'label' => '100,000 credits', 'credits' => 100000, 'price_cents' => 49900, 'badge' => 'Best value',
    ]))->assertCreated()->assertJsonPath('data.key', 'jumbo');

    expect(CreditPackDefinition::query()->pluck('key')->sort()->values()->all())->toBe(['jumbo', 'large', 'small'])
        ->and(array_keys(app(PackCatalog::class)->sellable()))->toBe(['small', 'large', 'jumbo'])
        ->and(app(PackCatalog::class)->find('jumbo')['badge'])->toBe('Best value')
        ->and(PricingChangeLog::query()->where('subject_type', 'pack')->count())->toBe(3);
});

it('edits a pack and leaves a deactivated one out of the catalog', function () {
    $pack = CreditPackDefinition::factory()->create(['key' => 'mid', 'credits' => 2000, 'price_cents' => 1900]);

    $this->withHeaders($this->headers)->patchJson(saUrl("pricing/packs/{$pack->id}"), stepUp($this->finance, ['price_cents' => 2400]))
        ->assertOk()->assertJsonPath('data.price_cents', 2400);

    expect(app(PackCatalog::class)->find('mid')['price_cents'])->toBe(2400);

    $this->withHeaders($this->headers)->patchJson(saUrl("pricing/packs/{$pack->id}"), stepUp($this->finance, ['is_active' => false]))->assertOk();

    expect(app(PackCatalog::class)->sellable())->toBe([]);
});

it('rejects a bad or duplicate pack key and a key change', function () {
    $pack = CreditPackDefinition::factory()->create(['key' => 'mid']);

    $this->withHeaders($this->headers)->postJson(saUrl('pricing/packs'), stepUp($this->finance, ['key' => 'mid', 'label' => 'x', 'credits' => 1, 'price_cents' => 100]))
        ->assertUnprocessable()->assertJsonValidationErrors('key');
    $this->withHeaders($this->headers)->postJson(saUrl('pricing/packs'), stepUp($this->finance, ['key' => 'Bad Key!', 'label' => 'x', 'credits' => 1, 'price_cents' => 100]))
        ->assertUnprocessable()->assertJsonValidationErrors('key');

    $this->withHeaders($this->headers)->patchJson(saUrl("pricing/packs/{$pack->id}"), stepUp($this->finance, ['key' => 'renamed', 'label' => 'New label']))->assertOk();

    expect($pack->fresh()->key)->toBe('mid')->and($pack->fresh()->label)->toBe('New label');
});

it('deletes an unbought pack but keeps one that has purchases', function () {
    $unbought = CreditPackDefinition::factory()->create(['key' => 'never-sold']);
    $bought = CreditPackDefinition::factory()->create(['key' => 'sold']);

    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Buyer']);
    CreditPack::factory()->create(['workspace_id' => $workspace->id, 'pack_key' => 'sold']);

    $this->withHeaders($this->headers)->deleteJson(saUrl("pricing/packs/{$unbought->id}"), stepUp($this->finance))->assertOk();
    $this->withHeaders($this->headers)->deleteJson(saUrl("pricing/packs/{$bought->id}"), stepUp($this->finance))->assertStatus(409);

    expect(CreditPackDefinition::query()->pluck('key')->all())->toBe(['sold']);
});

it('requires step-up and the prices ability to change packs', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('pricing/packs'), ['key' => 'x', 'label' => 'x', 'credits' => 1, 'price_cents' => 100])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'totp_code']);

    $operator = superAdmin(SuperAdminRole::Operator);
    $this->withHeaders(superAdminHeaders($operator))
        ->postJson(saUrl('pricing/packs'), stepUp($operator, ['key' => 'x', 'label' => 'x', 'credits' => 1, 'price_cents' => 100]))->assertForbidden();
});

it('reports every interval as on sale by default, from config', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('pricing/intervals'))
        ->assertOk()
        ->assertJsonPath('data.monthly', true)
        ->assertJsonPath('data.quarterly', true)
        ->assertJsonPath('data.yearly', true)
        ->assertJsonPath('data.lifetime', true);
});

it('switches an interval off estate-wide, which withdraws it from every plan', function () {
    $plan = Plan::factory()->soldForLifetime()->create(['slug' => 'pro']);

    expect($plan->isSoldOn(BillingInterval::Lifetime))->toBeTrue();

    $this->withHeaders($this->headers)->putJson(saUrl('pricing/intervals'), stepUp($this->finance, ['intervals' => ['lifetime' => false]]))
        ->assertOk()->assertJsonPath('data.lifetime', false)->assertJsonPath('data.monthly', true);

    expect($plan->fresh()->isSoldOn(BillingInterval::Lifetime))->toBeFalse()
        ->and($plan->fresh()->isSoldOn(BillingInterval::Monthly))->toBeTrue()
        ->and(PricingChangeLog::query()->where('subject_type', 'interval')->sole()->after['lifetime'])->toBeFalse();

    $this->withHeaders($this->headers)->putJson(saUrl('pricing/intervals'), stepUp($this->finance, ['intervals' => ['lifetime' => true]]))->assertOk();

    expect($plan->fresh()->isSoldOn(BillingInterval::Lifetime))->toBeTrue();
});

it('lets the database switch win over config, and config apply when no switch is stored', function () {
    config(['billing.intervals.quarterly' => false]);

    expect(BillingInterval::Quarterly->isEnabledGlobally())->toBeFalse();

    $this->withHeaders($this->headers)->putJson(saUrl('pricing/intervals'), stepUp($this->finance, ['intervals' => ['quarterly' => true]]))->assertOk();

    expect(BillingInterval::Quarterly->isEnabledGlobally())->toBeTrue();
});

it('rejects unknown intervals and requires step-up', function () {
    $this->withHeaders($this->headers)->putJson(saUrl('pricing/intervals'), stepUp($this->finance, ['intervals' => ['weekly' => false]]))
        ->assertUnprocessable()->assertJsonValidationErrors('intervals');
    $this->withHeaders($this->headers)->putJson(saUrl('pricing/intervals'), ['intervals' => ['lifetime' => false]])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'totp_code']);
});
