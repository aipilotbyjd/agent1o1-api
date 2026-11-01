<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Billing\PricingChangeLog;
use App\Services\Billing\BillingSettings;

beforeEach(function () {
    $this->finance = superAdmin(SuperAdminRole::Finance);
    $this->headers = superAdminHeaders($this->finance);
});

it('reports the credit policies from config until an admin changes one', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('pricing/credit-policies'))
        ->assertOk()
        ->assertJsonPath('data.policies.free_credits.one_per_owner', true)
        ->assertJsonPath('data.policies.abuse.enforcement', 'enforce')
        ->assertJsonPath('data.free_tier_spend_today_usd', 0);
});

it('changes a credit policy and records it in the pricing change log', function () {
    $this->withHeaders($this->headers)
        ->putJson(saUrl('pricing/credit-policies'), stepUp($this->finance, ['policies' => [
            'abuse' => ['enforcement' => 'shadow'],
            'guardrails' => ['free_tier_daily_budget_usd' => 25],
        ]]))
        ->assertOk()
        ->assertJsonPath('data.policies.abuse.enforcement', 'shadow');

    expect(app(BillingSettings::class)->get('abuse.enforcement'))->toBe('shadow')
        ->and(app(BillingSettings::class)->get('guardrails.free_tier_daily_budget_usd'))->toEqual(25)
        ->and(PricingChangeLog::query()->where('subject_type', 'credit_policy')->sole()->after['abuse.enforcement'])->toBe('shadow');
});

it('rejects an unknown policy or value and requires step-up', function () {
    $this->withHeaders($this->headers)->putJson(saUrl('pricing/credit-policies'), stepUp($this->finance, ['policies' => ['abuse' => ['enforcement' => 'sometimes']]]))
        ->assertUnprocessable()->assertJsonValidationErrors('policies.abuse.enforcement');
    $this->withHeaders($this->headers)->putJson(saUrl('pricing/credit-policies'), stepUp($this->finance, ['policies' => ['billing' => ['free' => true]]]))
        ->assertUnprocessable()->assertJsonValidationErrors('policies');
    $this->withHeaders($this->headers)->putJson(saUrl('pricing/credit-policies'), ['policies' => ['abuse' => ['enforcement' => 'shadow']]])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'totp_code']);
});
