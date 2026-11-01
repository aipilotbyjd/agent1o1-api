<?php

use App\Enums\Auth\AuthEvent;
use App\Enums\Broadcasts\BroadcastCategory;
use App\Enums\Workspaces\Role;
use App\Models\Auth\AuthEventLog;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Broadcasts\BroadcastAudience;

beforeEach(function () {
    $this->audience = app(BroadcastAudience::class);

    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $this->pro = Plan::factory()->create(['slug' => 'pro', 'credits_monthly' => 25000]);
});

/**
 * @param  list<User>  $users
 * @return list<string>
 */
function audienceEmails(?array $audience, BroadcastCategory $category = BroadcastCategory::Marketing): array
{
    $emails = [];

    app(BroadcastAudience::class)->each($audience, $category, function ($users) use (&$emails): void {
        foreach ($users as $user) {
            $emails[] = $user->email;
        }
    });

    sort($emails);

    return $emails;
}

it('reaches every workspace owner by default, and counts them', function () {
    $owner = referralUser();
    $member = User::factory()->create();
    $owner->ownedWorkspaces()->first()->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    User::factory()->create();

    expect(audienceEmails([]))->toBe([$owner->email])
        ->and($this->audience->count([], BroadcastCategory::Marketing))->toBe(1);
});

it('can reach members as well as owners', function () {
    $owner = referralUser();
    $member = User::factory()->create();
    $owner->ownedWorkspaces()->first()->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);

    expect(audienceEmails(['roles' => 'all']))->toEqualCanonicalizing([$owner->email, $member->email]);
});

it('filters by the effective plan, including a grant', function () {
    $free = referralUser();
    $granted = referralUser();
    PlanGrant::factory()->forWorkspace($granted->ownedWorkspaces()->first())->forPlan($this->pro)->active()->create();

    expect(audienceEmails(['plans' => ['pro']]))->toBe([$granted->email])
        ->and(audienceEmails(['plans' => ['free']]))->toBe([$free->email])
        ->and(audienceEmails(['plans' => ['free', 'pro']]))->toEqualCanonicalizing([$free->email, $granted->email]);
});

it('does not count an expired or revoked grant as the plan', function () {
    $lapsed = referralUser();
    PlanGrant::factory()->forWorkspace($lapsed->ownedWorkspaces()->first())->forPlan($this->pro)->expired()->create();

    expect(audienceEmails(['plans' => ['pro']]))->toBe([])
        ->and(audienceEmails(['plans' => ['free']]))->toBe([$lapsed->email]);
});

it('limits to the named workspaces', function () {
    $in = referralUser();
    referralUser();

    expect(audienceEmails(['workspace_ids' => [$in->ownedWorkspaces()->first()->id]]))->toBe([$in->email]);
});

it('finds people who have gone quiet, by sign-in and by runs', function () {
    $quiet = referralUser(['created_at' => now()->subDays(90)]);
    $signedIn = referralUser(['created_at' => now()->subDays(90)]);
    $running = referralUser(['created_at' => now()->subDays(90)]);
    $brandNew = referralUser();

    AuthEventLog::query()->create(['user_id' => $signedIn->id, 'email' => $signedIn->email, 'event' => AuthEvent::LoggedIn]);
    Run::factory()->create(['workspace_id' => $running->ownedWorkspaces()->first()->id]);

    expect(audienceEmails(['inactive_days' => 30]))->toBe([$quiet->email]);

    // An old sign-in doesn't count as activity.
    AuthEventLog::query()->where('user_id', $signedIn->id)->update(['created_at' => now()->subDays(60)]);

    expect(audienceEmails(['inactive_days' => 30]))->toEqualCanonicalizing([$quiet->email, $signedIn->email])
        ->and(audienceEmails(['inactive_days' => 30]))->not->toContain($brandNew->email);
});

it('counts a member of an active workspace as active', function () {
    $owner = referralUser(['created_at' => now()->subDays(90)]);
    $member = User::factory()->create(['created_at' => now()->subDays(90)]);
    $workspace = $owner->ownedWorkspaces()->first();
    $workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    Run::factory()->create(['workspace_id' => $workspace->id]);

    expect(audienceEmails(['roles' => 'all', 'inactive_days' => 30]))->toBe([]);
});

it('leaves out suspended and unverified people, and the opted out only for marketing', function () {
    $good = referralUser();
    referralUser(['suspended_at' => now()]);
    referralUser(['email_verified_at' => null]);
    $optedOut = referralUser(['marketing_opted_out_at' => now()]);

    expect(audienceEmails([]))->toBe([$good->email])
        ->and(audienceEmails([], BroadcastCategory::Service))->toEqualCanonicalizing([$good->email, $optedOut->email]);
});

it('samples at most ten people, in a stable order', function () {
    foreach (range(1, 12) as $ignored) {
        referralUser();
    }

    $sample = $this->audience->sample([], BroadcastCategory::Marketing);

    expect($sample)->toHaveCount(10)
        ->and($this->audience->count([], BroadcastCategory::Marketing))->toBe(12)
        ->and($sample->pluck('id')->all())->toBe($sample->pluck('id')->sort()->values()->all());
});

it('normalises a definition, dropping unknown keys and bad values', function () {
    expect($this->audience->normalize(['plans' => ['pro'], 'roles' => 'everyone', 'nonsense' => 1, 'inactive_days' => '0']))
        ->toBe(['plans' => ['pro'], 'roles' => 'owners', 'workspace_ids' => [], 'inactive_days' => null]);
});

it('matches a banner audience for one member', function () {
    $owner = referralUser();
    $member = User::factory()->create();
    $owner->ownedWorkspaces()->first()->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);

    expect($this->audience->matches($member, null))->toBeTrue()
        ->and($this->audience->matches($member, []))->toBeTrue()
        ->and($this->audience->matches($member, ['plans' => ['free']]))->toBeTrue()
        ->and($this->audience->matches($member, ['plans' => ['pro']]))->toBeFalse()
        ->and($this->audience->matches($member, ['roles' => 'owners']))->toBeFalse()
        ->and($this->audience->matches($owner, ['roles' => 'owners', 'plans' => ['free']]))->toBeTrue();
});
