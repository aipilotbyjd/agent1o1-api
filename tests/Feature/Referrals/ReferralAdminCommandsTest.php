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
use Illuminate\Support\Str;

it('creates a program and edits its settings, merging fraud checks', function () {
    $this->artisan('referrals:program', ['action' => 'create', '--set' => ['name=Launch Week', 'default_hold_days=7']])
        ->assertSuccessful();

    $program = ReferralProgram::query()->where('slug', 'launch-week')->sole();
    $program->update(['fraud_checks' => ['card_fingerprint' => false]]);

    $this->artisan('referrals:program', [
        'action' => 'update',
        'program' => 'launch-week',
        '--set' => ['fraud_checks.ip_velocity.max=3', 'is_active=false', 'referrer_monthly_credit_cap=null'],
    ])->assertSuccessful();

    $program->refresh();

    expect($program->default_hold_days)->toBe(7)
        ->and($program->is_active)->toBeFalse()
        ->and($program->fraudCheck('ip_velocity')['max'])->toBe(3)
        ->and($program->fraudCheckEnabled('card_fingerprint'))->toBeFalse()
        ->and(SuperAdminAuditLog::query()->pluck('action')->all())->toBe(['referral_program.created', 'referral_program.updated'])
        ->and(SuperAdminAuditLog::query()->whereNotNull('super_admin_id')->count())->toBe(0);
});

it('rejects invalid settings with the same rules as the admin API', function () {
    ReferralProgram::factory()->create(['slug' => 'default']);

    $this->artisan('referrals:program', ['action' => 'update', 'program' => 'default', '--set' => ['default_hold_days=1000']])
        ->expectsOutputToContain('default_hold_days')
        ->assertFailed();

    $this->artisan('referrals:program', ['action' => 'update', 'program' => 'default', '--set' => ['nonsense']])
        ->assertFailed();

    $this->artisan('referrals:program', ['action' => 'show', 'program' => 'missing'])->assertFailed();
});

it('moves the default, duplicates and deletes programs', function () {
    $default = ReferralProgram::factory()->asDefault()->create(['slug' => 'default']);
    ReferralRewardRule::factory()->forProgram($default)->count(2)->create();
    $other = ReferralProgram::factory()->create(['slug' => 'other']);

    $this->artisan('referrals:program', ['action' => 'delete', 'program' => 'default', '--force' => true])
        ->expectsOutputToContain('Make another program the default')
        ->assertFailed();

    $this->artisan('referrals:program', ['action' => 'default', 'program' => 'other'])->assertSuccessful();
    expect($other->fresh()->is_default)->toBeTrue()->and($default->fresh()->is_default)->toBeFalse();

    $this->artisan('referrals:program', ['action' => 'duplicate', 'program' => 'default', '--name' => 'Creators'])->assertSuccessful();
    $copy = ReferralProgram::query()->where('slug', 'creators')->sole();
    expect($copy->rules()->count())->toBe(2)->and($copy->is_active)->toBeFalse();

    $this->artisan('referrals:program', ['action' => 'delete', 'program' => 'creators', '--force' => true])->assertSuccessful();
    expect(ReferralProgram::query()->where('slug', 'creators')->exists())->toBeFalse();
});

it('adds, edits, switches, moves and deletes rules by short id', function () {
    $program = ReferralProgram::factory()->create(['slug' => 'default']);
    Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro']);
    $first = ReferralRewardRule::factory()->forProgram($program)->create(['sort_order' => 0]);

    $this->artisan('referrals:rule', [
        'action' => 'add',
        'target' => 'default',
        '--set' => ['name=Month of Pro', 'trigger=first_payment', 'recipient=referrer', 'reward_type=plan_time', 'plan=pro', 'duration_days=30', 'conditions.billing_intervals=monthly,yearly'],
    ])->expectsOutputToContain('You get 30 days of Pro free')->assertSuccessful();

    $rule = ReferralRewardRule::query()->where('name', 'Month of Pro')->sole();
    $short = Str::substr($rule->id, -8);

    expect($rule->conditions['billing_intervals'])->toBe(['monthly', 'yearly']);

    $this->artisan('referrals:rule', ['action' => 'update', 'target' => $short, '--set' => ['duration_days=60']])->assertSuccessful();
    $this->artisan('referrals:rule', ['action' => 'toggle', 'target' => $short])->assertSuccessful();
    $this->artisan('referrals:rule', ['action' => 'move', 'target' => $short, '--position' => 0])->assertSuccessful();

    $rule->refresh();
    expect($rule->duration_days)->toBe(60)
        ->and($rule->is_active)->toBeFalse()
        ->and($rule->sort_order)->toBe(0)
        ->and($first->fresh()->sort_order)->toBe(1);

    $this->artisan('referrals:rule', ['action' => 'delete', 'target' => $short, '--force' => true])->assertSuccessful();
    expect(ReferralRewardRule::query()->whereKey($rule->id)->exists())->toBeFalse();
});

it('refuses a rule that could never pay out', function () {
    ReferralProgram::factory()->create(['slug' => 'default']);

    $this->artisan('referrals:rule', [
        'action' => 'add',
        'target' => 'default',
        '--set' => ['name=Broken', 'trigger=first_payment', 'recipient=referrer', 'reward_type=plan_time', 'duration_days=30'],
    ])->expectsOutputToContain('A plan-time reward needs a plan')->assertFailed();

    expect(ReferralRewardRule::query()->count())->toBe(0);
});

it('overrides a referrer code found by email', function () {
    $program = ReferralProgram::factory()->create(['slug' => 'creators']);
    $owner = referralUser(['email' => 'jane@acme.test']);

    $this->artisan('referrals:code', [
        'code' => 'jane@acme.test',
        '--set' => ['code=Jane', 'program=creators', 'rule_multiplier=2', 'max_uses=50'],
    ])->assertSuccessful();

    $code = app(ReferralCodes::class)->forUser($owner);

    expect($code->code)->toBe('jane')
        ->and($code->program_id)->toBe($program->id)
        ->and($code->rule_multiplier)->toBe(2.0)
        ->and($code->max_uses)->toBe(50);

    $this->artisan('referrals:code', ['code' => 'jane'])->expectsOutputToContain('creators')->assertSuccessful();
    $this->artisan('referrals:code', ['code' => 'nobody'])->assertFailed();
});

it('lists, rejects and restores referrals', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['require_verified_email' => false]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(400)->create();
    $referral = referUser(referralUser(['email' => 'ref@acme.test']), program: $program);
    $short = Str::substr($referral->id, -8);

    $this->artisan('referrals:referral', ['action' => 'list', '--referrer' => 'ref@acme.test'])
        ->expectsOutputToContain($short)
        ->assertSuccessful();

    $this->artisan('referrals:referral', ['action' => 'reject', 'referral' => $short])->assertFailed();
    $this->artisan('referrals:referral', ['action' => 'reject', 'referral' => $short, '--reason' => 'Same person'])->assertSuccessful();

    expect($referral->fresh()->status)->toBe(ReferralStatus::Rejected)
        ->and($referral->rewards()->first()->status)->toBe(ReferralRewardStatus::Revoked);

    $this->artisan('referrals:referral', ['action' => 'restore', 'referral' => $short])->assertSuccessful();
    $this->artisan('referrals:referral', ['action' => 'show', 'referral' => $short])->assertSuccessful();

    expect($referral->fresh()->status)->toBe(ReferralStatus::Verified);
});

it('lists, approves and grants rewards early', function () {
    $program = ReferralProgram::factory()->asDefault()->create([
        'require_verified_email' => false,
        'approval_mode' => ReferralApprovalMode::Manual,
    ]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(400)->create();
    $referral = referUser(referralUser(), program: $program);
    $reward = ReferralReward::query()->sole();
    $short = Str::substr($reward->id, -8);

    $this->artisan('referrals:reward', ['action' => 'list', '--status' => 'awaiting_approval'])
        ->expectsOutputToContain($short)
        ->assertSuccessful();

    $this->artisan('referrals:reward', ['action' => 'approve', 'reward' => $short])
        ->expectsOutput('Approved and granted.')
        ->assertSuccessful();

    expect($referral->referredWorkspace->fresh()->topup_credits)->toBe(400);

    $this->artisan('referrals:reward', ['action' => 'grant-now', 'reward' => $short])
        ->expectsOutputToContain('Only a pending or awaiting reward')
        ->assertFailed();
});

it('blocks and unblocks email domains', function () {
    $this->artisan('referrals:domain', ['action' => 'add', 'domain' => 'Spam.Example', '--reason' => 'Throwaway'])->assertSuccessful();
    $this->artisan('referrals:domain', ['action' => 'add', 'domain' => 'spam.example'])->assertFailed();
    $this->artisan('referrals:domain', ['action' => 'add', 'domain' => 'not a domain'])->assertFailed();
    $this->artisan('referrals:domain', ['action' => 'list'])->expectsOutputToContain('spam.example')->assertSuccessful();

    expect(ReferralBlockedDomain::query()->sole()->domain)->toBe('spam.example');

    $this->artisan('referrals:domain', ['action' => 'remove', 'domain' => 'spam.example'])->assertSuccessful();
    $this->artisan('referrals:domain', ['action' => 'remove', 'domain' => 'spam.example'])->assertFailed();

    expect(ReferralBlockedDomain::query()->count())->toBe(0);
});

it('reports stats and the audit log', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['require_verified_email' => false]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(400)->create();
    referUser(referralUser(), program: $program);

    $this->artisan('referrals:stats', ['--days' => 7])->expectsOutputToContain('last 7 days')->assertSuccessful();

    $this->artisan('referrals:domain', ['action' => 'add', 'domain' => 'spam.example'])->assertSuccessful();

    $this->artisan('super-admin:audit-log', ['--action' => 'referral_blocked_domain'])
        ->expectsOutputToContain('cli:')
        ->assertSuccessful();
});
