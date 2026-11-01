<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanPriceHistory;
use App\Models\Billing\PricingChangeLog;

beforeEach(function () {
    $this->finance = superAdmin(SuperAdminRole::Finance);
    $this->headers = superAdminHeaders($this->finance);
    $this->stripe = fakePricingStripe();

    $this->plan = Plan::factory()->withoutLifetime()->create([
        'slug' => 'pro',
        'stripe_product_id' => 'prod_pro',
        'stripe_price_id_monthly' => 'price_old_monthly',
        'stripe_price_id_yearly' => 'price_old_yearly',
        'stripe_price_id_quarterly' => null,
        'price_monthly' => 2900,
        'price_yearly' => 29000,
        'price_quarterly' => 0,
        'price_lifetime' => 0,
    ]);

    $this->url = saUrl("pricing/plans/{$this->plan->id}/prices");
});

it('creates a new Stripe price, keeps the old id in history, and archives the old price', function () {
    $this->withHeaders($this->headers)->putJson($this->url, stepUp($this->finance, ['prices' => ['monthly' => 3900]]))
        ->assertOk()
        ->assertJsonPath('data.prices.monthly', 3900)
        ->assertJsonPath('data.stripe.prices.monthly', 'price_fake_1');

    $plan = $this->plan->fresh();

    expect($plan->price_monthly)->toBe(3900)
        ->and($plan->stripe_price_id_monthly)->toBe('price_fake_1')
        ->and($plan->price_yearly)->toBe(29000)
        ->and($plan->stripe_price_id_yearly)->toBe('price_old_yearly')
        ->and($this->stripe->callsTo('archivePrice'))->toBe([['archivePrice', 'price_old_monthly']]);

    $old = PlanPriceHistory::query()->where('stripe_price_id', 'price_old_monthly')->sole();
    $new = PlanPriceHistory::query()->where('stripe_price_id', 'price_fake_1')->sole();

    expect($old->amount_cents)->toBe(2900)
        ->and($old->retired_at)->not->toBeNull()
        ->and($new->amount_cents)->toBe(3900)
        ->and($new->retired_at)->toBeNull()
        ->and($new->created_by)->toBe($this->finance->id);

    $log = PricingChangeLog::query()->where('action', 'prices_changed')->sole();

    expect($log->before['price_monthly'])->toBe(2900)
        ->and($log->after['price_monthly'])->toBe(3900)
        ->and($log->reason)->toBe('Verified request from the account owner');
});

it('still resolves a subscriber on a retired price to their plan', function () {
    $this->withHeaders($this->headers)->putJson($this->url, stepUp($this->finance, ['prices' => ['monthly' => 3900]]))->assertOk();

    expect(Plan::findByStripePriceId('price_old_monthly')?->id)->toBe($this->plan->id)
        ->and(Plan::findByStripePriceId('price_fake_1')?->id)->toBe($this->plan->id)
        ->and(Plan::findByStripePriceId('price_never_heard_of'))->toBeNull();
});

it('creates the Stripe product when the plan has none', function () {
    $this->plan->forceFill(['stripe_product_id' => null])->save();

    $this->withHeaders($this->headers)->putJson($this->url, stepUp($this->finance, ['prices' => ['monthly' => 3900]]))->assertOk();

    expect($this->plan->fresh()->stripe_product_id)->toStartWith('prod_fake_')
        ->and($this->stripe->callsTo('createProduct'))->toHaveCount(1);
});

it('prices an interval that had no Stripe price yet, with nothing to retire', function () {
    $this->withHeaders($this->headers)->putJson($this->url, stepUp($this->finance, ['prices' => ['quarterly' => 7900]]))->assertOk();

    $plan = $this->plan->fresh();

    expect($plan->price_quarterly)->toBe(7900)
        ->and($plan->stripe_price_id_quarterly)->toStartWith('price_fake_')
        ->and($this->stripe->callsTo('archivePrice'))->toBe([])
        ->and(PlanPriceHistory::query()->count())->toBe(1);
});

it('does nothing for amounts that are already current', function () {
    $this->withHeaders($this->headers)->putJson($this->url, stepUp($this->finance, ['prices' => ['monthly' => 2900, 'yearly' => 29000]]))->assertOk();

    expect($this->stripe->calls)->toBe([])
        ->and(PricingChangeLog::query()->count())->toBe(0)
        ->and(PlanPriceHistory::query()->count())->toBe(0);
});

it('refuses to price a sold interval at zero', function () {
    $this->withHeaders($this->headers)->putJson($this->url, stepUp($this->finance, ['prices' => ['monthly' => 0]]))->assertUnprocessable();

    expect($this->plan->fresh()->price_monthly)->toBe(2900)
        ->and($this->stripe->calls)->toBe([]);
});

it('leaves the plan untouched and cleans up Stripe when a later price fails', function () {
    $this->stripe->failPriceAt = 2;

    $this->withHeaders($this->headers)->putJson($this->url, stepUp($this->finance, ['prices' => ['monthly' => 3900, 'yearly' => 39000]]))
        ->assertStatus(500);

    $plan = $this->plan->fresh();

    expect($plan->price_monthly)->toBe(2900)
        ->and($plan->stripe_price_id_monthly)->toBe('price_old_monthly')
        ->and($plan->price_yearly)->toBe(29000)
        ->and(PlanPriceHistory::query()->count())->toBe(0)
        ->and(PricingChangeLog::query()->count())->toBe(0)
        // The one price that was made is archived again; the old ones never are.
        ->and($this->stripe->callsTo('archivePrice'))->toBe([['archivePrice', 'price_fake_1']]);
});

it('passes a stable idempotency key so a retry cannot make a second price', function () {
    $this->withHeaders($this->headers)->putJson($this->url, stepUp($this->finance, ['prices' => ['monthly' => 3900]]))->assertOk();

    expect($this->stripe->prices()[0][3])->toBe("plan-price:{$this->plan->id}:monthly:3900:price_old_monthly");
});

it('replays the first answer for a repeated Idempotency-Key instead of changing prices twice', function () {
    $headers = [...$this->headers, 'Idempotency-Key' => 'price-change-1'];
    $payload = stepUp($this->finance, ['prices' => ['monthly' => 3900]]);

    $this->withHeaders($headers)->putJson($this->url, $payload)->assertOk();
    $this->withHeaders($headers)->putJson($this->url, [...$payload, 'totp_code' => superAdminTotp($this->finance)])->assertOk();

    expect($this->stripe->prices())->toHaveCount(1)
        ->and(PricingChangeLog::query()->where('action', 'prices_changed')->count())->toBe(1);
});

it('requires step-up, the right ability and a valid body', function () {
    $this->withHeaders($this->headers)->putJson($this->url, ['prices' => ['monthly' => 3900]])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'totp_code']);

    $this->withHeaders($this->headers)->putJson($this->url, stepUp($this->finance, ['prices' => ['weekly' => 100]]))
        ->assertUnprocessable()->assertJsonValidationErrors('prices');

    $this->withHeaders($this->headers)->putJson($this->url, stepUp($this->finance, ['prices' => ['monthly' => -5]]))
        ->assertUnprocessable()->assertJsonValidationErrors('prices.monthly');

    $operator = superAdmin(SuperAdminRole::Operator);
    $this->withHeaders(superAdminHeaders($operator))->putJson($this->url, stepUp($operator, ['prices' => ['monthly' => 3900]]))->assertForbidden();

    expect($this->stripe->calls)->toBe([]);
});

it('dry-runs a sync without writing, reporting what is missing or off', function () {
    $this->stripe->inspections['price_old_monthly'] = ['exists' => true, 'active' => true, 'amount_cents' => 2900];
    $this->stripe->inspections['price_old_yearly'] = ['exists' => true, 'active' => true, 'amount_cents' => 31000];
    $this->plan->forceFill(['price_quarterly' => 7900])->save();

    $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$this->plan->id}/stripe/sync"), ['dry_run' => true])
        ->assertOk()
        ->assertJsonPath('data.applied', false)
        ->assertJsonPath('data.product', 'ok')
        ->assertJsonPath('data.prices.monthly', 'ok')
        ->assertJsonPath('data.prices.quarterly', 'missing')
        ->assertJsonPath('data.prices.yearly', 'amount_mismatch')
        ->assertJsonPath('data.prices.lifetime', 'unpriced');

    expect($this->stripe->calls)->toBe([]);
});

it('syncs a plan that has an amount but no Stripe objects', function () {
    $draft = Plan::factory()->create([
        'slug' => 'draft',
        'price_monthly' => 1900, 'price_quarterly' => 0, 'price_yearly' => 0, 'price_lifetime' => 0,
        'stripe_product_id' => null,
        'stripe_price_id_monthly' => null, 'stripe_price_id_quarterly' => null, 'stripe_price_id_yearly' => null, 'stripe_price_id_lifetime' => null,
    ]);

    $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$draft->id}/stripe/sync"), stepUp($this->finance))
        ->assertOk()
        ->assertJsonPath('data.applied', true)
        ->assertJsonPath('data.prices.monthly', 'created');

    $draft = $draft->fresh();

    expect($draft->stripe_product_id)->toStartWith('prod_fake_')
        ->and($draft->stripe_price_id_monthly)->toStartWith('price_fake_')
        ->and($this->stripe->prices())->toHaveCount(1);
});

it('requires step-up for a real sync', function () {
    $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$this->plan->id}/stripe/sync"), [])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'totp_code']);
});
