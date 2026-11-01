<?php

use App\Enums\Referrals\ReferralApprovalMode;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralStatus;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\Plan;
use App\Models\Referrals\ReferralBlockedDomain;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralRewardRule;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\Referrals\ReferralCodes;

beforeEach(function () {
    $this->admin = superAdmin();
    $this->withHeaders(superAdminHeaders($this->admin));
});

it('creates a program and records it in the audit log', function () {
    $this->postJson('/api/super-admin/v1/referrals/programs', [
        'name' => 'Summer Campaign',
        'default_hold_days' => 7,
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addMonth()->toIso8601String(),
        'fraud_checks' => ['ip_velocity' => ['max' => 3]],
    ])
        ->assertCreated()
        ->assertJsonPath('data.program.slug', 'summer-campaign')
        ->assertJsonPath('data.program.default_hold_days', 7)
        ->assertJsonPath('data.program.fraud_checks.ip_velocity.max', 3)
        ->assertJsonPath('data.program.fraud_checks.ip_velocity.hours', 24);

    expect(SuperAdminAuditLog::query()->sole()->action)->toBe('referral_program.created');
});

it('validates program settings', function (array $input, string $error) {
    $this->postJson('/api/super-admin/v1/referrals/programs', ['name' => 'X', ...$input])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'hold too long' => [['default_hold_days' => 1000], 'default_hold_days'],
    'unknown approval mode' => [['approval_mode' => 'sometimes'], 'approval_mode'],
    'unknown fraud check' => [['fraud_checks' => ['make_it_up' => true]], 'fraud_checks'],
    'ends before it starts' => [['starts_at' => '2026-10-10', 'ends_at' => '2026-10-01'], 'ends_at'],
]);

it('updates a program, merging fraud checks and logging only what changed', function () {
    $program = ReferralProgram::factory()->create(['fraud_checks' => ['card_fingerprint' => false, 'ip_velocity' => ['max' => 9]]]);

    $this->patchJson("/api/super-admin/v1/referrals/programs/{$program->id}", [
        'default_hold_days' => 30,
        'fraud_checks' => ['ip_velocity' => ['hours' => 6]],
    ])->assertSuccessful();

    $program->refresh();
    $log = SuperAdminAuditLog::query()->sole();

    expect($program->default_hold_days)->toBe(30)
        ->and($program->fraudCheck('ip_velocity'))->toMatchArray(['max' => 9, 'hours' => 6])
        ->and($program->fraudCheckEnabled('card_fingerprint'))->toBeFalse()
        ->and($log->action)->toBe('referral_program.updated')
        ->and($log->after)->toHaveKey('default_hold_days', 30)
        ->and($log->before)->toHaveKey('default_hold_days', 0);
});

it('moves the default to another program', function () {
    $old = ReferralProgram::factory()->asDefault()->create();
    $new = ReferralProgram::factory()->create();

    $this->postJson("/api/super-admin/v1/referrals/programs/{$new->id}/make-default")->assertSuccessful();

    expect($new->fresh()->is_default)->toBeTrue()
        ->and($old->fresh()->is_default)->toBeFalse();
});

it('refuses to delete the default program', function () {
    $program = ReferralProgram::factory()->asDefault()->create();

    $this->deleteJson("/api/super-admin/v1/referrals/programs/{$program->id}")->assertUnprocessable();
});

it('duplicates a program with its rules, switched off', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['name' => 'Default']);
    ReferralRewardRule::factory()->forProgram($program)->count(2)->create();

    $copyId = $this->postJson("/api/super-admin/v1/referrals/programs/{$program->id}/duplicate")
        ->assertCreated()
        ->assertJsonPath('data.program.is_active', false)
        ->assertJsonPath('data.program.is_default', false)
        ->assertJsonCount(2, 'data.program.rules')
        ->json('data.program.id');

    expect(ReferralProgram::find($copyId)->slug)->toBe('default-copy');
});

it('creates, edits, toggles, reorders and deletes rules', function () {
    $program = ReferralProgram::factory()->create();
    $pro = Plan::factory()->create(['name' => 'Pro']);

    $ruleId = $this->postJson("/api/super-admin/v1/referrals/programs/{$program->id}/rules", [
        'name' => 'Month of Pro',
        'trigger' => 'first_payment',
        'recipient' => 'referrer',
        'reward_type' => 'plan_time',
        'plan_id' => $pro->id,
        'duration_days' => 30,
        'conditions' => ['billing_intervals' => ['yearly']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.rule.summary', 'You get 30 days of Pro free when your friend makes their first payment on a yearly plan.')
        ->json('data.rule.id');

    $this->patchJson("/api/super-admin/v1/referrals/rules/{$ruleId}", ['duration_days' => 60])
        ->assertSuccessful()
        ->assertJsonPath('data.rule.duration_days', 60);

    $this->postJson("/api/super-admin/v1/referrals/rules/{$ruleId}/toggle")->assertJsonPath('data.rule.is_active', false);

    $other = ReferralRewardRule::factory()->forProgram($program)->create(['sort_order' => 5]);
    $this->patchJson("/api/super-admin/v1/referrals/programs/{$program->id}/rules/reorder", ['rule_ids' => [$other->id, $ruleId]])
        ->assertSuccessful()
        ->assertJsonPath('data.rules.0.id', $other->id);

    $this->deleteJson("/api/super-admin/v1/referrals/rules/{$ruleId}")->assertSuccessful();

    expect(ReferralRewardRule::query()->whereKey($ruleId)->exists())->toBeFalse()
        ->and(ReferralRewardRule::withTrashed()->whereKey($ruleId)->exists())->toBeTrue();
});

it('refuses rules that could never pay out', function (array $input, string $error) {
    $program = ReferralProgram::factory()->create();

    $this->postJson("/api/super-admin/v1/referrals/programs/{$program->id}/rules", [
        'name' => 'Broken',
        'trigger' => 'first_payment',
        'recipient' => 'referrer',
        ...$input,
    ])->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'plan time without a plan' => [['reward_type' => 'plan_time', 'duration_days' => 30], 'plan_id'],
    'credits without an amount' => [['reward_type' => 'credits'], 'credits_amount'],
    'invoice credit without an amount' => [['reward_type' => 'stripe_balance_credit'], 'amount_cents'],
    'trial extension for the referrer' => [['reward_type' => 'trial_extension', 'trial_days' => 7], 'recipient'],
    'milestone without a count' => [['trigger' => 'milestone', 'reward_type' => 'credits', 'credits_amount' => 10], 'milestone_count'],
    'manual trigger' => [['trigger' => 'manual', 'reward_type' => 'credits', 'credits_amount' => 10], 'trigger'],
]);

it('checks an edit against the rule as it would be saved', function () {
    $rule = ReferralRewardRule::factory()->create();

    $this->patchJson("/api/super-admin/v1/referrals/rules/{$rule->id}", ['reward_type' => 'plan_time'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan_id');
});

it('applies a rule change to the very next reward', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['require_verified_email' => false]);
    $rule = ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(100)->create();

    referUser(referralUser(), program: $program);

    $this->patchJson("/api/super-admin/v1/referrals/rules/{$rule->id}", ['credits_amount' => 900])->assertSuccessful();

    referUser(referralUser(), program: $program);

    expect(ReferralReward::query()->orderBy('created_at')->pluck('credits')->all())->toBe([100, 900]);
});

it('simulates a program without writing anything', function () {
    $program = ReferralProgram::factory()->create(['default_hold_days' => 14]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(1000)->create();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::FirstPayment)->credits(50)->create(['conditions' => ['min_payment_cents' => 50000]]);

    $this->postJson("/api/super-admin/v1/referrals/programs/{$program->id}/simulate", [
        'trigger' => 'first_payment',
        'payment_cents' => 9900,
        'multiplier' => 2,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.results.0.applies', true)
        ->assertJsonPath('data.results.0.reward.credits', 2000)
        ->assertJsonPath('data.results.0.initial_status', 'pending')
        ->assertJsonPath('data.results.1.applies', false);

    expect(ReferralReward::query()->count())->toBe(0);
});

it('approves, grants early and revokes rewards', function () {
    $program = ReferralProgram::factory()->asDefault()->create([
        'require_verified_email' => false,
        'approval_mode' => ReferralApprovalMode::Manual,
    ]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(400)->create();
    $referral = referUser(referralUser(), program: $program);
    $reward = ReferralReward::query()->sole();
    $workspace = $referral->referredWorkspace;

    expect($reward->status)->toBe(ReferralRewardStatus::AwaitingApproval);

    $this->getJson('/api/super-admin/v1/referrals/rewards?status=awaiting_approval')->assertJsonCount(1, 'data');

    $this->postJson("/api/super-admin/v1/referrals/rewards/{$reward->id}/approve")
        ->assertSuccessful()
        ->assertJsonPath('data.reward.status', 'granted');
    expect($workspace->fresh()->topup_credits)->toBe(400);

    $this->postJson("/api/super-admin/v1/referrals/rewards/{$reward->id}/revoke", [])->assertJsonValidationErrors('reason');
    $this->postJson("/api/super-admin/v1/referrals/rewards/{$reward->id}/revoke", ['reason' => 'Duplicate account'])
        ->assertSuccessful()
        ->assertJsonPath('data.reward.status', 'revoked');
    expect($workspace->fresh()->topup_credits)->toBe(0);

    expect(SuperAdminAuditLog::query()->pluck('action')->all())->toBe(['referral_reward.approved', 'referral_reward.revoked']);
});

it('grants a held reward early', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['require_verified_email' => false]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(400)->create(['hold_days' => 30]);
    referUser(referralUser(), program: $program);
    $reward = ReferralReward::query()->sole();

    $this->postJson("/api/super-admin/v1/referrals/rewards/{$reward->id}/grant-now")->assertJsonPath('data.reward.status', 'granted');
});

it('hands out a goodwill reward', function () {
    $user = referralUser();
    $pro = Plan::factory()->create(['credits_monthly' => 25000]);

    $this->postJson('/api/super-admin/v1/referrals/rewards/manual', [
        'user_id' => $user->id,
        'reward_type' => 'plan_time',
        'plan_id' => $pro->id,
        'duration_days' => 14,
        'notes' => 'Sorry about the outage',
    ])
        ->assertCreated()
        ->assertJsonPath('data.reward.status', 'granted')
        ->assertJsonPath('data.reward.trigger', 'manual')
        ->assertJsonPath('data.reward.granted_by', $this->admin->id);

    expect($user->currentWorkspace->fresh()->currentPlan()->id)->toBe($pro->id);

    $this->postJson('/api/super-admin/v1/referrals/rewards/manual', [
        'user_id' => $user->id,
        'workspace_id' => referralUser()->current_workspace_id,
        'reward_type' => 'credits',
        'credits' => 10,
    ])->assertUnprocessable()->assertJsonValidationErrors('workspace_id');
});

it('rejects and restores a referral', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['require_verified_email' => false]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(400)->create();
    $referral = referUser(referralUser(), program: $program);

    $this->postJson("/api/super-admin/v1/referrals/referrals/{$referral->id}/reject", ['reason' => 'Same person'])
        ->assertSuccessful()
        ->assertJsonPath('data.referral.status', 'rejected')
        ->assertJsonPath('data.referral.rewards.0.status', 'revoked');

    $this->postJson("/api/super-admin/v1/referrals/referrals/{$referral->id}/restore")
        ->assertSuccessful()
        ->assertJsonPath('data.referral.status', ReferralStatus::Verified->value);

    $this->getJson("/api/super-admin/v1/referrals/referrals/{$referral->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.referral.referred_user.email', $referral->referredUser->email);
});

it('overrides a referrer\'s code', function () {
    $program = ReferralProgram::factory()->create();
    $code = app(ReferralCodes::class)->forUser(referralUser(['email' => 'creator@acme.test']));

    $this->getJson('/api/super-admin/v1/referrals/codes?search=creator')->assertJsonPath('data.0.id', $code->id);

    $this->patchJson("/api/super-admin/v1/referrals/codes/{$code->id}", [
        'code' => 'CREATOR',
        'program_id' => $program->id,
        'rule_multiplier' => 2,
        'max_uses' => 100,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.code.code', 'creator')
        ->assertJsonPath('data.code.program_id', $program->id);
});

it('maintains the blocked-domain list', function () {
    $id = $this->postJson('/api/super-admin/v1/referrals/blocked-domains', ['domain' => ' Spam.Example ', 'reason' => 'Throwaway'])
        ->assertCreated()
        ->assertJsonPath('data.domain.domain', 'spam.example')
        ->json('data.domain.id');

    $this->postJson('/api/super-admin/v1/referrals/blocked-domains', ['domain' => 'spam.example'])->assertUnprocessable();
    $this->postJson('/api/super-admin/v1/referrals/blocked-domains', ['domain' => 'not a domain'])->assertUnprocessable();

    $this->deleteJson("/api/super-admin/v1/referrals/blocked-domains/{$id}")->assertSuccessful();

    expect(ReferralBlockedDomain::query()->count())->toBe(0);
});

it('reports platform-wide referral numbers', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['require_verified_email' => false]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(400)->create();
    referUser(referralUser(), program: $program);

    $this->getJson('/api/super-admin/v1/referrals/stats?days=7')
        ->assertSuccessful()
        ->assertJsonPath('data.signups', 1)
        ->assertJsonPath('data.credits_issued', 400)
        ->assertJsonPath('data.window_days', 7);
});

it('lists the audit log', function () {
    $this->postJson('/api/super-admin/v1/referrals/programs', ['name' => 'Logged'])->assertCreated();

    $this->getJson('/api/super-admin/v1/audit-log')
        ->assertSuccessful()
        ->assertJsonPath('data.0.action', 'referral_program.created')
        ->assertJsonPath('data.0.actor.id', $this->admin->id);
});
