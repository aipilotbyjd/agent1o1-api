<?php

use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Enums\Referrals\AlreadyOnPlanBehavior;
use App\Enums\Referrals\ReferralApprovalMode;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralStatus;
use App\Enums\Referrals\ReferralTrigger;
use App\Events\Runs\RunCompleted;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\Subscription;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralRewardRule;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Referrals\PaymentFingerprintChecker;
use App\Services\Referrals\ReferralCodes;
use App\Services\Referrals\ReferralLifecycle;
use App\Services\Workspaces\WorkspaceService;

/**
 * A default program (no hold unless given), a Free default plan and a Pro
 * plan, a referrer, and a verified referral whose workspace is a Stripe
 * customer.
 *
 * @return array{program: ReferralProgram, pro: Plan, referral: Referral, referredWorkspace: Workspace, referrerWorkspace: Workspace}
 */
function paidReferralSetup(array $programAttributes = []): array
{
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro', 'credits_monthly' => 25000, 'price_monthly' => 9900, 'stripe_price_id_monthly' => 'price_pro_monthly']);

    $program = ReferralProgram::factory()->asDefault()->create($programAttributes);
    $referrer = referralUser();
    $referral = referUser($referrer, referralUser(), $program);

    $referredWorkspace = $referral->referredWorkspace;
    $referredWorkspace->forceFill(['stripe_id' => 'cus_referred'])->save();

    return [
        'program' => $program,
        'pro' => $pro,
        'referral' => $referral->fresh(),
        'referredWorkspace' => $referredWorkspace->fresh(),
        'referrerWorkspace' => $referrer->currentWorkspace,
    ];
}

function subscribeReferredWorkspace(Workspace $workspace, Plan $plan): void
{
    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_price_id_monthly,
        'quantity' => 1,
    ]);
}

it('rewards the referrer when the referred workspace completes its first run', function () {
    ['program' => $program, 'referral' => $referral, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::Activated)->credits(1000)->create();

    $workflow = Workflow::factory()->forWorkspace($referral->referredWorkspace)->create();
    $run = Run::factory()->forWorkflow($workflow)->completed()->create();

    event(new RunCompleted($run));
    event(new RunCompleted($run));

    expect($referral->fresh()->status)->toBe(ReferralStatus::Activated)
        ->and($referrerWorkspace->fresh()->topup_credits)->toBe(1000)
        ->and(ReferralReward::query()->count())->toBe(1);
});

it('does not count runs from before the activation window closed', function () {
    ['program' => $program, 'referral' => $referral] = paidReferralSetup(['activation_window_days' => 7]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::Activated)->credits(1000)->create();

    $this->travel(8)->days();

    $workflow = Workflow::factory()->forWorkspace($referral->referredWorkspace)->create();
    event(new RunCompleted(Run::factory()->forWorkflow($workflow)->completed()->create()));

    expect($referral->fresh()->status)->toBe(ReferralStatus::Verified);
});

it('only counts the run types the program chose', function () {
    ['program' => $program, 'referral' => $referral] = paidReferralSetup(['activation_event' => 'first_agent_session']);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::Activated)->credits(1000)->create();

    $workflow = Workflow::factory()->forWorkspace($referral->referredWorkspace)->create();
    event(new RunCompleted(Run::factory()->forWorkflow($workflow)->completed()->create()));

    expect($referral->fresh()->status)->toBe(ReferralStatus::Verified);
});

it('converts on the first paid invoice and grants free plan time to the referrer', function () {
    ['program' => $program, 'pro' => $pro, 'referral' => $referral, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->planTime($pro, 30)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_first', 'cus_referred', 9900))->assertOk();

    $grant = PlanGrant::query()->where('workspace_id', $referrerWorkspace->id)->first();

    expect($referral->fresh()->status)->toBe(ReferralStatus::Converted)
        ->and($grant->source)->toBe(PlanGrantSource::Referral)
        ->and($grant->status)->toBe(PlanGrantStatus::Active)
        ->and((int) round(now()->diffInDays($grant->expires_at)))->toBe(30)
        ->and($referrerWorkspace->fresh()->currentPlan()->id)->toBe($pro->id)
        ->and($referrerWorkspace->fresh()->currentUsagePeriod()->credits_limit)->toBe(25000);
});

it('stacks a second plan-time reward onto the same grant', function () {
    ['program' => $program, 'pro' => $pro, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->planTime($pro, 30)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_a', 'cus_referred', 9900))->assertOk();

    $second = referUser($referrerWorkspace->owner, referralUser(), $program);
    $second->referredWorkspace->forceFill(['stripe_id' => 'cus_second'])->save();
    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_b', 'cus_second', 9900))->assertOk();

    $grants = PlanGrant::query()->where('workspace_id', $referrerWorkspace->id)->get();

    expect($grants)->toHaveCount(1)
        ->and((int) round(now()->diffInDays($grants->first()->expires_at)))->toBe(60);
});

it('caps stacked plan time for the referrer', function () {
    ['program' => $program, 'pro' => $pro, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup(['referrer_max_stacked_plan_days' => 45]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->planTime($pro, 30)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_a', 'cus_referred', 9900))->assertOk();
    $second = referUser($referrerWorkspace->owner, referralUser(), $program);
    $second->referredWorkspace->forceFill(['stripe_id' => 'cus_second'])->save();
    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_b', 'cus_second', 9900))->assertOk();

    $grant = PlanGrant::query()->where('workspace_id', $referrerWorkspace->id)->first();

    expect((int) round(now()->diffInDays($grant->expires_at)))->toBe(45)
        ->and(ReferralReward::query()->orderBy('created_at')->pluck('duration_days')->all())->toBe([30, 15]);
});

it('gives a paying subscriber an invoice credit instead of plan time they already have', function () {
    $balance = fakeStripeBalance();
    ['program' => $program, 'pro' => $pro, 'referredWorkspace' => $referredWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment, ReferralRecipient::Referee)->planTime($pro, 30)
        ->create(['if_already_on_plan' => AlreadyOnPlanBehavior::StripeBalanceCredit]);
    subscribeReferredWorkspace($referredWorkspace, $pro);

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_sub', 'cus_referred', 9900))->assertOk();

    $reward = ReferralReward::query()->sole();

    expect($reward->reward_type)->toBe(ReferralRewardType::StripeBalanceCredit)
        ->and($reward->amount_cents)->toBe(9900)
        ->and($reward->status)->toBe(ReferralRewardStatus::Granted)
        ->and($balance->credits)->toBe([['workspace' => $referredWorkspace->id, 'cents' => 9900]])
        ->and(PlanGrant::query()->count())->toBe(0);
});

it('converts plan time to credits for a workspace already on the plan when told to', function () {
    ['program' => $program, 'pro' => $pro, 'referredWorkspace' => $referredWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment, ReferralRecipient::Referee)->planTime($pro, 30)
        ->create(['if_already_on_plan' => AlreadyOnPlanBehavior::ConvertToCredits, 'fallback_credits' => 4000]);
    subscribeReferredWorkspace($referredWorkspace, $pro);

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_sub', 'cus_referred', 9900))->assertOk();

    expect($referredWorkspace->fresh()->topup_credits)->toBe(4000);
});

it('skips plan time a workspace already has when told to', function () {
    ['program' => $program, 'pro' => $pro, 'referredWorkspace' => $referredWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment, ReferralRecipient::Referee)->planTime($pro, 30)
        ->create(['if_already_on_plan' => AlreadyOnPlanBehavior::Skip]);
    subscribeReferredWorkspace($referredWorkspace, $pro);

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_sub', 'cus_referred', 9900))->assertOk();

    expect(ReferralReward::query()->count())->toBe(0);
});

it('holds payment rewards until the hold passes, then grants them', function () {
    ['program' => $program, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup(['default_hold_days' => 14]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(2000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_hold', 'cus_referred', 9900))->assertOk();

    expect(ReferralReward::query()->sole()->status)->toBe(ReferralRewardStatus::Pending);

    $this->artisan('referrals:grant-pending')->assertSuccessful();
    expect($referrerWorkspace->fresh()->topup_credits)->toBe(0);

    $this->travel(15)->days();
    $this->artisan('referrals:grant-pending')->assertSuccessful();

    expect($referrerWorkspace->fresh()->topup_credits)->toBe(2000)
        ->and(ReferralReward::query()->sole()->status)->toBe(ReferralRewardStatus::Granted);
});

it('parks rewards for approval in a manual-approval program', function () {
    ['program' => $program, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup(['approval_mode' => ReferralApprovalMode::Manual]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(2000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_manual', 'cus_referred', 9900))->assertOk();
    $this->artisan('referrals:grant-pending')->assertSuccessful();

    expect(ReferralReward::query()->sole()->status)->toBe(ReferralRewardStatus::AwaitingApproval)
        ->and($referrerWorkspace->fresh()->topup_credits)->toBe(0);
});

it('ignores a redelivered invoice and a zero-amount trial invoice', function () {
    ['program' => $program, 'referral' => $referral, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(2000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_trial', 'cus_referred', 0))->assertOk();
    expect($referral->fresh()->status)->toBe(ReferralStatus::Verified);

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_paid', 'cus_referred', 9900))->assertOk();
    // A new Stripe event id carrying the same invoice.
    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_paid_again', 'cus_referred', 9900, ['id' => 'in_evt_paid']))->assertOk();

    expect($referrerWorkspace->fresh()->topup_credits)->toBe(2000)
        ->and($referral->payments()->count())->toBe(1);
});

it('rewards repeat payments up to the rule limit', function () {
    ['program' => $program, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::RepeatPayment)->credits(100)->create(['max_per_recipient' => 2]);

    foreach (range(1, 4) as $month) {
        $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload("evt_{$month}", 'cus_referred', 9900))->assertOk();
    }

    // Payments 2 and 3 pay out; the first is `first_payment`, the fourth is over the limit.
    expect($referrerWorkspace->fresh()->topup_credits)->toBe(200);
});

it('applies rule conditions to the payment', function () {
    ['program' => $program, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(5000)->create([
        'conditions' => ['billing_intervals' => ['yearly']],
    ]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(700)->create([
        'conditions' => ['min_payment_cents' => 5000],
    ]);

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_monthly', 'cus_referred', 9900, [
        'lines' => ['data' => [['price' => ['id' => 'price_pro_monthly']]]],
    ]))->assertOk();

    expect($referrerWorkspace->fresh()->topup_credits)->toBe(700);
});

it('excludes tax from the payment amount', function () {
    ['program' => $program, 'referral' => $referral] = paidReferralSetup();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_tax', 'cus_referred', 12000, [
        'total' => 12000,
        'total_excluding_tax' => 10000,
    ]))->assertOk();

    expect($referral->payments()->sole()->amount_cents)->toBe(10000);
});

it('scales referrer rewards by the code multiplier', function () {
    ['program' => $program, 'referral' => $referral, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup();
    $referral->code->update(['rule_multiplier' => 2.5]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(1000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_mult', 'cus_referred', 9900))->assertOk();

    expect($referrerWorkspace->fresh()->topup_credits)->toBe(2500);
});

it('trims referrer credits to the monthly cap', function () {
    ['program' => $program, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup(['referrer_monthly_credit_cap' => 1500]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(1000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_a', 'cus_referred', 9900))->assertOk();
    $second = referUser($referrerWorkspace->owner, referralUser(), $program);
    $second->referredWorkspace->forceFill(['stripe_id' => 'cus_second'])->save();
    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_b', 'cus_second', 9900))->assertOk();

    expect($referrerWorkspace->fresh()->topup_credits)->toBe(1500);
});

it('pays a milestone once when the referrer reaches it', function () {
    ['program' => $program, 'pro' => $pro, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->milestone(2)->credits(9000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_a', 'cus_referred', 9900))->assertOk();
    expect($referrerWorkspace->fresh()->topup_credits)->toBe(0);

    foreach (['cus_second', 'cus_third'] as $customer) {
        $next = referUser($referrerWorkspace->owner, referralUser(), $program);
        $next->referredWorkspace->forceFill(['stripe_id' => $customer])->save();
        $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload("evt_{$customer}", $customer, 9900))->assertOk();
    }

    expect($referrerWorkspace->fresh()->topup_credits)->toBe(9000);
});

it('keeps an earned reward\'s terms when the rule is later edited', function () {
    ['program' => $program] = paidReferralSetup(['default_hold_days' => 14]);
    $rule = ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(1000)->create();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_snap', 'cus_referred', 9900))->assertOk();

    $rule->update(['credits_amount' => 5]);

    $reward = ReferralReward::query()->sole();

    expect($reward->credits)->toBe(1000)
        ->and($reward->rule_snapshot['credits_amount'])->toBe(1000);
});

it('stops rewarding once the program is switched off', function () {
    ['program' => $program, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(1000)->create();

    $program->update(['is_active' => false]);

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_off', 'cus_referred', 9900))->assertOk();

    expect($referrerWorkspace->fresh()->topup_credits)->toBe(0);
});

it('rejects the conversion when the referred workspace pays with the referrer\'s card', function () {
    ['program' => $program, 'referral' => $referral] = paidReferralSetup(['fraud_checks' => ['card_fingerprint' => true]]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(1000)->create();

    app()->instance(PaymentFingerprintChecker::class, new class extends PaymentFingerprintChecker
    {
        public function sharesCardWithReferrer(Referral $referral): bool
        {
            return true;
        }
    });

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_card', 'cus_referred', 9900))->assertOk();

    expect($referral->fresh()->status)->toBe(ReferralStatus::Rejected)
        ->and(ReferralReward::query()->count())->toBe(0);
});

it('never fails the webhook when the referral step throws', function () {
    paidReferralSetup();

    app()->instance(ReferralLifecycle::class, Mockery::mock(ReferralLifecycle::class, function ($mock): void {
        $mock->shouldReceive('recordPayment')->andThrow(new RuntimeException('boom'));
    }));

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_boom', 'cus_referred', 9900))->assertOk();
});

it('counts a later workspace the referred user owns toward their referral', function () {
    ['program' => $program, 'referral' => $referral, 'referrerWorkspace' => $referrerWorkspace] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(1000)->create();

    $second = app(WorkspaceService::class)->create($referral->referredUser, ['name' => 'Side project']);
    $second->forceFill(['stripe_id' => 'cus_side'])->save();

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_side', 'cus_side', 9900))->assertOk();

    expect($referrerWorkspace->fresh()->topup_credits)->toBe(1000);
});

it('credits the referrer\'s chosen reward workspace', function () {
    ['program' => $program, 'referral' => $referral] = paidReferralSetup();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(1000)->create();
    $other = app(WorkspaceService::class)->create($referral->referrer, ['name' => 'Rewards go here']);
    app(ReferralCodes::class)->forUser($referral->referrer)->update(['reward_workspace_id' => $other->id]);

    $this->postJson('/api/stripe/webhook', referralInvoicePaidPayload('evt_ws', 'cus_referred', 9900))->assertOk();

    expect($other->fresh()->topup_credits)->toBe(1000);
});
