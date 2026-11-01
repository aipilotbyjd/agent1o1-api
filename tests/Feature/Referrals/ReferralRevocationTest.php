<?php

use App\Enums\Billing\PlanGrantStatus;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralStatus;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Referrals\PendingReferralRefund;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralRewardRule;
use App\Services\Referrals\ReferralLifecycle;

/**
 * @return array{program: ReferralProgram, referral: Referral}
 */
function refundableReferral(array $programAttributes = []): array
{
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);

    $program = ReferralProgram::factory()->asDefault()->create($programAttributes);
    $referral = referUser(referralUser(), referralUser(), $program);
    $referral->referredWorkspace->forceFill(['stripe_id' => 'cus_refund'])->save();

    return ['program' => $program, 'referral' => $referral->fresh()];
}

function refundPayload(string $eventId, string $paymentIntent, bool $full = true): array
{
    return [
        'id' => $eventId,
        'type' => 'charge.refunded',
        'data' => ['object' => [
            'id' => 'ch_'.$eventId,
            'payment_intent' => $paymentIntent,
            'refunded' => $full,
            'amount' => 9900,
            'amount_refunded' => $full ? 9900 : 1000,
        ]],
    ];
}

it('cancels a held reward when its payment is refunded', function () {
    ['program' => $program] = refundableReferral(['default_hold_days' => 14]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(2000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_pay', 'cus_refund', 9900))->assertOk();
    $this->postJson('/api/stripe/webhook', refundPayload('evt_refund', 'pi_evt_pay'))->assertOk();

    $this->travel(15)->days();
    $this->artisan('referrals:grant-pending')->assertSuccessful();

    $reward = ReferralReward::query()->sole();

    expect($reward->status)->toBe(ReferralRewardStatus::Revoked)
        ->and($reward->revoked_reason)->toBe('Payment refunded')
        ->and($reward->workspace->topup_credits)->toBe(0);
});

it('claws granted credits back, but never below zero', function () {
    ['program' => $program, 'referral' => $referral] = refundableReferral();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(2000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_pay', 'cus_refund', 9900))->assertOk();

    $workspace = $referral->referrer->currentWorkspace;
    $workspace->decrement('topup_credits', 1500);

    $this->postJson('/api/stripe/webhook', refundPayload('evt_refund', 'pi_evt_pay'))->assertOk();

    expect($workspace->fresh()->topup_credits)->toBe(0)
        ->and(ReferralReward::query()->sole()->credits_clawed_back)->toBe(500);
});

it('ignores a partial refund unless the program says otherwise', function () {
    ['program' => $program] = refundableReferral();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(2000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_pay', 'cus_refund', 9900))->assertOk();
    $this->postJson('/api/stripe/webhook', refundPayload('evt_partial', 'pi_evt_pay', full: false))->assertOk();

    expect(ReferralReward::query()->sole()->status)->toBe(ReferralRewardStatus::Granted);

    $program->update(['revoke_on_partial_refund' => true]);
    $this->postJson('/api/stripe/webhook', refundPayload('evt_partial_2', 'pi_evt_pay', full: false))->assertOk();

    expect(ReferralReward::query()->sole()->status)->toBe(ReferralRewardStatus::Revoked);
});

it('withdraws rewards when a payment is disputed', function () {
    ['program' => $program] = refundableReferral();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(2000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_pay', 'cus_refund', 9900))->assertOk();
    $this->postJson('/api/stripe/webhook', [
        'id' => 'evt_dispute',
        'type' => 'charge.dispute.created',
        'data' => ['object' => ['id' => 'dp_1', 'payment_intent' => 'pi_evt_pay']],
    ])->assertOk();

    expect(ReferralReward::query()->sole()->revoked_reason)->toBe('Payment disputed');
});

it('shortens granted plan time on refund, revoking the grant once nothing is left', function () {
    ['program' => $program, 'referral' => $referral] = refundableReferral();
    $pro = Plan::factory()->create(['credits_monthly' => 25000]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->planTime($pro, 30)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_pay', 'cus_refund', 9900))->assertOk();
    $this->postJson('/api/stripe/webhook', refundPayload('evt_refund', 'pi_evt_pay'))->assertOk();

    $grant = PlanGrant::query()->sole();

    expect($grant->status)->toBe(PlanGrantStatus::Revoked)
        ->and($referral->referrer->currentWorkspace->fresh()->currentPlan()->slug)->toBe('free');
});

it('keeps stacked time from other rewards when one is refunded', function () {
    ['program' => $program, 'referral' => $referral] = refundableReferral();
    $pro = Plan::factory()->create(['credits_monthly' => 25000]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->planTime($pro, 30)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_pay', 'cus_refund', 9900))->assertOk();
    $second = referUser($referral->referrer, referralUser(), $program);
    $second->referredWorkspace->forceFill(['stripe_id' => 'cus_other'])->save();
    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_other', 'cus_other', 9900))->assertOk();

    $this->postJson('/api/stripe/webhook', refundPayload('evt_refund', 'pi_evt_pay'))->assertOk();

    $grant = PlanGrant::query()->sole();

    expect($grant->status)->toBe(PlanGrantStatus::Active)
        ->and((int) round(now()->diffInDays($grant->expires_at)))->toBe(30);
});

it('withdraws every reward of a referral an admin rejects', function () {
    ['program' => $program, 'referral' => $referral] = refundableReferral(['require_verified_email' => false]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(500)->create();
    referUser(referralUser(), $fresh = referralUser(), $program);
    $rejectedReferral = $fresh->referral;

    app(ReferralLifecycle::class)->reject($rejectedReferral, 'Fraud');

    expect($rejectedReferral->fresh()->status)->toBe(ReferralStatus::Rejected)
        ->and($rejectedReferral->rewards()->first()->status)->toBe(ReferralRewardStatus::Revoked)
        ->and($fresh->currentWorkspace->fresh()->topup_credits)->toBe(0);
});

it('maps a refund back through an invoice that only lists its payment under payments', function () {
    ['program' => $program] = refundableReferral();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(2000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_new_api', 'cus_refund', 9900, [
        'payment_intent' => null,
        'payments' => ['data' => [['payment' => ['payment_intent' => 'pi_new_api']]]],
    ]))->assertOk();
    $this->postJson('/api/stripe/webhook', refundPayload('evt_refund', 'pi_new_api'))->assertOk();

    expect(ReferralReward::query()->sole()->status)->toBe(ReferralRewardStatus::Revoked);
});

it('withdraws a payment whose refund arrived before the payment was recorded', function () {
    ['program' => $program] = refundableReferral();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(2000)->create();

    $this->postJson('/api/stripe/webhook', refundPayload('evt_early_refund', 'pi_evt_pay'))->assertOk();
    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_pay', 'cus_refund', 9900))->assertOk();

    expect(ReferralReward::query()->count())->toBe(0)
        ->and(PendingReferralRefund::query()->count())->toBe(0);
});

it('never moves a referral backwards or out of rejection', function () {
    ['referral' => $referral] = refundableReferral();
    $referral->forceFill(['status' => ReferralStatus::Converted, 'converted_at' => now()])->save();

    app(ReferralLifecycle::class)->markVerified(Referral::find($referral->id));

    expect($referral->fresh()->status)->toBe(ReferralStatus::Converted);

    $referral->forceFill(['status' => ReferralStatus::Rejected, 'rejected_at' => now()])->save();

    app(ReferralLifecycle::class)->markActivated(Referral::find($referral->id));

    expect($referral->fresh()->status)->toBe(ReferralStatus::Rejected);
});
