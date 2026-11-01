<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Billing\Plan;
use App\Models\Billing\PricingChangeLog;
use App\Models\Billing\PricingCoupon;

beforeEach(function () {
    $this->finance = superAdmin(SuperAdminRole::Finance);
    $this->headers = superAdminHeaders($this->finance);
    $this->stripe = fakePricingStripe();
});

it('creates a percentage coupon in Stripe, with a promotion code, and records it', function () {
    $response = $this->withHeaders($this->headers)->postJson(saUrl('pricing/coupons'), stepUp($this->finance, [
        'name' => 'Launch 20',
        'code' => 'launch20',
        'percent_off' => 20,
        'duration' => 'repeating',
        'duration_in_months' => 3,
        'max_redemptions' => 100,
    ]))->assertCreated();

    $response->assertJsonPath('data.code', 'LAUNCH20')->assertJsonPath('data.stripe_coupon_id', 'coupon_fake_1')->assertJsonPath('data.stripe_promotion_code_id', 'promo_fake_2');

    $coupon = $this->stripe->callsTo('createCoupon')[0][1];
    $promo = $this->stripe->callsTo('createPromotionCode')[0][1];

    expect($coupon)->toBe(['name' => 'Launch 20', 'duration' => 'repeating', 'percent_off' => 20, 'duration_in_months' => 3])
        ->and($promo)->toBe(['coupon' => 'coupon_fake_1', 'code' => 'LAUNCH20', 'max_redemptions' => 100])
        ->and(PricingCoupon::query()->where('code', 'LAUNCH20')->sole()->is_active)->toBeTrue()
        ->and(PricingChangeLog::query()->where('subject_type', 'coupon')->where('action', 'created')->count())->toBe(1);
});

it('creates a fixed-amount coupon limited to specific plans', function () {
    $plan = Plan::factory()->create(['slug' => 'pro', 'stripe_product_id' => 'prod_pro']);

    $this->withHeaders($this->headers)->postJson(saUrl('pricing/coupons'), stepUp($this->finance, [
        'name' => 'Ten off', 'code' => 'TENOFF', 'amount_off_cents' => 1000, 'duration' => 'once', 'plan_ids' => [$plan->id],
    ]))->assertCreated()->assertJsonPath('data.plan_ids', [$plan->id]);

    expect($this->stripe->callsTo('createCoupon')[0][1])->toBe([
        'name' => 'Ten off', 'duration' => 'once', 'amount_off' => 1000, 'currency' => 'usd', 'applies_to' => ['products' => ['prod_pro']],
    ]);
});

it('refuses to limit a coupon to a plan that has no Stripe product', function () {
    $plan = Plan::factory()->create(['slug' => 'draft', 'stripe_product_id' => null]);

    $this->withHeaders($this->headers)->postJson(saUrl('pricing/coupons'), stepUp($this->finance, [
        'name' => 'X', 'code' => 'XCODE', 'percent_off' => 10, 'duration' => 'once', 'plan_ids' => [$plan->id],
    ]))->assertUnprocessable();

    expect($this->stripe->calls)->toBe([])->and(PricingCoupon::query()->count())->toBe(0);
});

it('validates the discount shape, code format and uniqueness', function () {
    PricingCoupon::factory()->create(['code' => 'TAKEN']);

    $post = fn (array $payload) => $this->withHeaders($this->headers)->postJson(saUrl('pricing/coupons'), stepUp($this->finance, [
        'name' => 'N', 'duration' => 'once', ...$payload,
    ]));

    $post(['code' => 'TAKEN', 'percent_off' => 10])->assertUnprocessable()->assertJsonValidationErrors('code');
    $post(['code' => 'bad code!', 'percent_off' => 10])->assertUnprocessable()->assertJsonValidationErrors('code');
    $post(['code' => 'NODISCOUNT'])->assertUnprocessable()->assertJsonValidationErrors(['percent_off', 'amount_off_cents']);
    $post(['code' => 'BOTH', 'percent_off' => 10, 'amount_off_cents' => 500])->assertUnprocessable()->assertJsonValidationErrors('percent_off');
    $post(['code' => 'OVER', 'percent_off' => 101])->assertUnprocessable()->assertJsonValidationErrors('percent_off');
    $post(['code' => 'NOMONTHS', 'percent_off' => 10, 'duration' => 'repeating'])->assertUnprocessable()->assertJsonValidationErrors('duration_in_months');
    $post(['code' => 'PAST', 'percent_off' => 10, 'redeem_by' => now()->subDay()->toIso8601String()])->assertUnprocessable()->assertJsonValidationErrors('redeem_by');
});

it('switches the promotion code off and on in Stripe', function () {
    $coupon = PricingCoupon::factory()->create(['code' => 'SPRING', 'stripe_promotion_code_id' => 'promo_spring']);

    $this->withHeaders($this->headers)->postJson(saUrl("pricing/coupons/{$coupon->id}/deactivate"), stepUp($this->finance))
        ->assertOk()->assertJsonPath('data.is_active', false);

    expect($coupon->fresh()->is_active)->toBeFalse()
        ->and($this->stripe->callsTo('setPromotionCodeActive'))->toBe([['setPromotionCodeActive', 'promo_spring', false]]);

    $this->withHeaders($this->headers)->postJson(saUrl("pricing/coupons/{$coupon->id}/activate"), stepUp($this->finance))
        ->assertOk()->assertJsonPath('data.is_active', true);

    expect($this->stripe->callsTo('setPromotionCodeActive')[1])->toBe(['setPromotionCodeActive', 'promo_spring', true]);
});

it('lists coupons, filters by active, and refreshes the redemption count on show', function () {
    $active = PricingCoupon::factory()->create(['code' => 'ACTIVE1']);
    PricingCoupon::factory()->create(['code' => 'OFF1', 'is_active' => false]);

    $this->withHeaders($this->headers)->getJson(saUrl('pricing/coupons'))->assertOk()->assertJsonCount(2, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('pricing/coupons?active=1'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'ACTIVE1');

    $this->withHeaders($this->headers)->getJson(saUrl("pricing/coupons/{$active->id}"))->assertOk()->assertJsonPath('data.times_redeemed', 7);

    expect($active->fresh()->times_redeemed)->toBe(7);
});

it('requires step-up and the prices ability', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('pricing/coupons'), ['name' => 'N', 'code' => 'ABC', 'percent_off' => 5, 'duration' => 'once'])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'totp_code']);

    $operator = superAdmin(SuperAdminRole::Operator);
    $this->withHeaders(superAdminHeaders($operator))
        ->postJson(saUrl('pricing/coupons'), stepUp($operator, ['name' => 'N', 'code' => 'ABC', 'percent_off' => 5, 'duration' => 'once']))->assertForbidden();

    expect($this->stripe->calls)->toBe([]);
});
