<?php

use App\Enums\Billing\PlanGrantSource;
use App\Enums\Referrals\ReferralApprovalMode;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralRewardRule;
use App\Models\Referrals\ReferralVisit;
use App\Notifications\Referrals\ReferralPlanTimeEndingNotification;
use App\Services\Notifications\AdminAlerts;
use App\Services\Referrals\ReferralCodes;
use App\Services\Referrals\ReferralTrialBonus;
use Illuminate\Support\Facades\Notification;

it('reminds a workspace once before its referral plan time ends', function () {
    Notification::fake();
    $user = referralUser();
    $grant = PlanGrant::factory()->forWorkspace($user->currentWorkspace)->forPlan(Plan::factory()->create())->active()->create([
        'source' => PlanGrantSource::Referral,
        'expires_at' => now()->addDays(2),
    ]);

    $this->artisan('referrals:notify-plan-time-ending')->assertSuccessful();
    $this->artisan('referrals:notify-plan-time-ending')->assertSuccessful();

    Notification::assertSentToTimes($user, ReferralPlanTimeEndingNotification::class, 1);
    expect($grant->fresh()->expiry_notified_at)->not->toBeNull();
});

it('prunes visits past the retention window', function () {
    $code = app(ReferralCodes::class)->forUser(referralUser());
    ReferralVisit::query()->create(['referral_code_id' => $code->id, 'visitor_id' => fake()->uuid()]);

    $this->travel(91)->days();
    ReferralVisit::query()->create(['referral_code_id' => $code->id, 'visitor_id' => fake()->uuid()]);

    $this->artisan('referrals:prune-visits')->assertSuccessful();

    expect(ReferralVisit::query()->count())->toBe(1);
});

it('sends the admin digest only when something needs attention', function () {
    $alerts = Mockery::mock(AdminAlerts::class);
    $alerts->shouldReceive('raise')->once()->andReturnTrue();
    app()->instance(AdminAlerts::class, $alerts);

    $this->artisan('referrals:admin-digest')->expectsOutput('Nothing needs attention.')->assertSuccessful();

    $program = ReferralProgram::factory()->asDefault()->create(['require_verified_email' => false, 'approval_mode' => ReferralApprovalMode::Manual]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(100)->create();
    referUser(referralUser(), program: $program);

    $this->artisan('referrals:admin-digest')->expectsOutput('Digest sent.')->assertSuccessful();
});

it('grants and revokes goodwill rewards from the command line', function () {
    $user = referralUser(['email' => 'vip@acme.test']);

    $this->artisan('referrals:grant', ['email' => 'vip@acme.test', '--credits' => 750, '--reason' => 'Beta tester'])->assertSuccessful();

    $reward = ReferralReward::query()->sole();
    expect($user->currentWorkspace->fresh()->topup_credits)->toBe(750)
        ->and($reward->notes)->toBe('Beta tester');

    $this->artisan('referrals:revoke', ['reward' => $reward->id, '--reason' => 'Mistake'])->assertSuccessful();

    expect($reward->fresh()->status)->toBe(ReferralRewardStatus::Revoked)
        ->and($user->currentWorkspace->fresh()->topup_credits)->toBe(0);

    $this->artisan('referrals:grant', ['email' => 'vip@acme.test'])->assertFailed();
    $this->artisan('referrals:grant', ['email' => 'vip@acme.test', '--plan' => 'missing', '--days' => 5])->assertFailed();
});

it('seeds a working default program without overwriting later edits', function () {
    Plan::factory()->create(['slug' => 'pro']);

    $this->syncSetup('referral-program');

    $program = ReferralProgram::query()->where('slug', 'default')->sole();
    expect($program->is_default)->toBeTrue()
        ->and($program->rules()->count())->toBe(7);

    $program->update(['default_hold_days' => 3]);
    $this->syncSetup('referral-program');

    expect($program->fresh()->default_hold_days)->toBe(3)
        ->and($program->rules()->count())->toBe(7);

    $this->artisan('referrals:programs')->assertSuccessful();
    $this->artisan('referrals:simulate', ['program' => 'default', 'trigger' => 'first_payment', '--payment-cents' => 9900])->assertSuccessful();
});

it('adds earned trial-extension days to checkout', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['require_verified_email' => false]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->trialExtension(7)->create();

    $referral = referUser(referralUser(), program: $program);

    expect(app(ReferralTrialBonus::class)->daysFor($referral->referredWorkspace))->toBe(7)
        ->and(app(ReferralTrialBonus::class)->daysFor(referralUser()->currentWorkspace))->toBe(0);
});
