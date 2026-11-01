<?php

use App\Actions\Referrals\AttributeReferralAction;
use App\Enums\Onboarding\DiscoverySource;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralStatus;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\Plan;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralBlockedDomain;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralRewardRule;
use App\Models\Referrals\ReferralVisit;
use App\Models\User;
use App\Notifications\Referrals\ReferralSignedUpNotification;
use App\Services\Referrals\ReferralCodes;
use App\Services\Referrals\ReferralSettings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Passport\Passport;

function registerWithReferral(string $email, ?string $code, ?string $visitorId = null): User
{
    test()->postJson('/api/v1/auth/register', array_filter([
        'name' => 'New Person',
        'email' => $email,
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'referral_code' => $code,
        'referral_visitor_id' => $visitorId,
    ]))->assertCreated();

    return User::query()->where('email', $email)->firstOrFail();
}

it('attributes an email signup to the referrer whose code it carried', function () {
    Notification::fake();
    $program = ReferralProgram::factory()->asDefault()->create();
    $referrer = referralUser();
    $code = app(ReferralCodes::class)->forUser($referrer);

    $referred = registerWithReferral('new@acme.test', strtoupper($code->code));

    $referral = $referred->referral;

    expect($referral)->not->toBeNull()
        ->and($referral->referrer_user_id)->toBe($referrer->id)
        ->and($referral->program_id)->toBe($program->id)
        ->and($referral->referred_workspace_id)->toBe($referred->current_workspace_id)
        ->and($referral->status)->toBe(ReferralStatus::Pending)
        ->and($referred->discovery_source)->toBe(DiscoverySource::Referral);

    Notification::assertSentTo($referrer, ReferralSignedUpNotification::class);
});

it('registers normally when the referral code is unknown', function () {
    ReferralProgram::factory()->asDefault()->create();

    $referred = registerWithReferral('new@acme.test', 'no-such-code');

    expect($referred->referral)->toBeNull();
});

it('fires the signup reward once the referred user verifies their email', function () {
    $program = ReferralProgram::factory()->asDefault()->create();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(500)->create();
    $referrer = referralUser();
    $code = app(ReferralCodes::class)->forUser($referrer);

    $referred = registerWithReferral('new@acme.test', $code->code);

    expect($referred->currentWorkspace->topup_credits)->toBe(0);

    $this->get(URL::temporarySignedRoute('auth.verify-email', now()->addHour(), [
        'id' => $referred->id,
        'hash' => sha1($referred->email),
    ]))->assertRedirect();

    $referral = $referred->referral->fresh();

    expect($referral->status)->toBe(ReferralStatus::Verified)
        ->and($referred->currentWorkspace->fresh()->topup_credits)->toBe(500)
        ->and($referral->rewards()->first()->status)->toBe(ReferralRewardStatus::Granted);
});

it('fires the signup trigger at once when the program does not require a verified email', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['require_verified_email' => false]);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(250)->create();

    $referral = referUser(referralUser(), program: $program);

    expect($referral->status)->toBe(ReferralStatus::Verified)
        ->and($referral->referredUser->currentWorkspace->topup_credits)->toBe(250);
});

it('rejects a signup from a blocked email domain but keeps the record', function () {
    ReferralBlockedDomain::query()->create(['domain' => 'mailinator.com']);

    $referral = referUser(referralUser(), referralUser(['email' => 'spam@mailinator.com']));

    expect($referral->status)->toBe(ReferralStatus::Rejected)
        ->and($referral->rejection_reason)->toContain('email domain');
});

it('rejects a signup that already shares a workspace with the referrer', function () {
    $referrer = referralUser();
    $referred = referralUser();
    $referrer->currentWorkspace->members()->create(['user_id' => $referred->id, 'role' => 'member', 'joined_at' => now()]);

    $referral = referUser($referrer, $referred);

    expect($referral->status)->toBe(ReferralStatus::Rejected);
});

it('rejects signups beyond the network velocity limit', function () {
    $program = ReferralProgram::factory()->asDefault()->create([
        'fraud_checks' => ['card_fingerprint' => false, 'ip_velocity' => ['enabled' => true, 'max' => 2, 'hours' => 24]],
    ]);
    $referrer = referralUser();
    $code = app(ReferralCodes::class)->forUser($referrer);
    $attribute = app(AttributeReferralAction::class);

    $rejected = collect(range(1, 3))->map(fn () => $attribute->execute(referralUser(), $code->code, null, '203.0.113.9')->isRejected());

    expect($rejected->all())->toBe([false, false, true]);
});

it('rejects a referrer who has reached the monthly referral limit', function () {
    $program = ReferralProgram::factory()->asDefault()->create(['referrer_max_referrals_per_month' => 1]);
    $referrer = referralUser();

    expect(referUser($referrer, program: $program)->status)->toBe(ReferralStatus::Pending)
        ->and(referUser($referrer, program: $program)->status)->toBe(ReferralStatus::Rejected);
});

it('attributes a user only once', function () {
    $program = ReferralProgram::factory()->asDefault()->create();
    $referred = referralUser();

    referUser(referralUser(), $referred, $program);

    expect(referUser(referralUser(), $referred, $program))->toBeNull()
        ->and(Referral::query()->count())->toBe(1);
});

it('does not attribute through an inactive or exhausted code', function () {
    ReferralProgram::factory()->asDefault()->create();
    $referrer = referralUser();
    $code = app(ReferralCodes::class)->forUser($referrer);

    $code->update(['is_active' => false]);
    expect(app(AttributeReferralAction::class)->execute(referralUser(), $code->code))->toBeNull();

    $code->update(['is_active' => true, 'max_uses' => 1]);
    referUser($referrer);
    expect(app(AttributeReferralAction::class)->execute(referralUser(), $code->code))->toBeNull();
});

it('does nothing when there is no live program', function () {
    ReferralProgram::factory()->asDefault()->inactive()->create();

    expect(referUser(referralUser(), program: ReferralProgram::query()->first()))->toBeNull();
});

it('does nothing when referrals are switched off', function () {
    config(['referrals.enabled' => false]);

    expect(referUser(referralUser()))->toBeNull();
});

it('pins a referral to the program its code points at', function () {
    ReferralProgram::factory()->asDefault()->create();
    $influencer = ReferralProgram::factory()->create();

    $referral = referUser(referralUser(), program: $influencer);

    expect($referral->program_id)->toBe($influencer->id);
});

it('records a link visit and links it to the signup', function () {
    ReferralProgram::factory()->asDefault()->create();
    $code = app(ReferralCodes::class)->forUser(referralUser());

    $visitorId = $this->postJson('/api/v1/referrals/visit', [
        'code' => $code->code,
        'landing_url' => 'https://app.test/?ref='.$code->code,
        'utm' => ['source' => 'twitter'],
    ])->assertCreated()->json('data.visitor_id');

    $referred = registerWithReferral('new@acme.test', $code->code, $visitorId);

    expect(ReferralVisit::query()->count())->toBe(1)
        ->and($referred->referral->visit_id)->toBe(ReferralVisit::query()->value('id'));
});

it('ignores a visit cookie older than the attribution window', function () {
    ReferralProgram::factory()->asDefault()->create(['attribution_window_days' => 30]);
    $code = app(ReferralCodes::class)->forUser(referralUser());
    $visitorId = fake()->uuid();
    ReferralVisit::query()->create(['referral_code_id' => $code->id, 'visitor_id' => $visitorId]);

    $this->travel(31)->days();

    $referred = registerWithReferral('late@acme.test', $code->code, $visitorId);

    expect($referred->referral)->toBeNull();
});

it('answers 404 for a visit on an unknown code', function () {
    $this->postJson('/api/v1/referrals/visit', ['code' => 'nope'])->assertNotFound();
});

it('lets a fresh social signup claim a referral', function () {
    $program = ReferralProgram::factory()->asDefault()->create();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(500)->create();
    $code = app(ReferralCodes::class)->forUser(referralUser());
    $referred = referralUser();

    Passport::actingAs($referred);

    $this->postJson('/api/v1/referrals/claim', ['code' => $code->code])
        ->assertCreated()
        ->assertJsonPath('data.accepted', true);

    // Social signups arrive verified, so the signup reward lands at once.
    expect($referred->currentWorkspace->fresh()->topup_credits)->toBe(500);

    $this->postJson('/api/v1/referrals/claim', ['code' => $code->code])->assertStatus(409);
});

it('refuses a claim outside the claim window', function () {
    ReferralProgram::factory()->asDefault()->create(['claim_window_hours' => 24]);
    $code = app(ReferralCodes::class)->forUser(referralUser());
    $referred = referralUser();

    $this->travel(25)->hours();
    Passport::actingAs($referred);

    $this->postJson('/api/v1/referrals/claim', ['code' => $code->code])->assertUnprocessable();
});

it('reads its cached settings back from a store that refuses to unserialize objects', function () {
    // The array store used elsewhere in tests keeps live objects; the real
    // stores serialize, and `cache.serializable_classes` is off.
    config(['cache.default' => 'database']);

    $program = ReferralProgram::factory()->asDefault()->create(['require_verified_email' => false]);
    $pro = Plan::factory()->create(['name' => 'Pro']);
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified, ReferralRecipient::Referee)->credits(500)->create();
    ReferralRewardRule::factory()->forProgram($program)->on(ReferralTrigger::SignupVerified)->planTime($pro, 30)->create();

    $settings = app(ReferralSettings::class);

    foreach ([1, 2] as $read) {
        $rules = $settings->rulesFor($settings->defaultProgram(), ReferralTrigger::SignupVerified);

        expect($settings->defaultProgram()->id)->toBe($program->id)
            ->and($rules)->toHaveCount(2)
            ->and($rules->last()->plan->name)->toBe('Pro');
    }

    $referral = referUser(referralUser(), program: $program);

    expect($referral->referredUser->currentWorkspace->topup_credits)->toBe(500);
});
