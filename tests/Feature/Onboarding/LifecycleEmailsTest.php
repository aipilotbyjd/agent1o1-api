<?php

use App\Enums\Onboarding\LifecycleEmail;
use App\Models\Onboarding\LifecycleEmailSend;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Notifications\Onboarding\LifecycleEmailNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

/**
 * A user who signed up `$ago` ago, with a workspace of their own.
 *
 * @param  array<string, mixed>  $attributes
 */
function lifecycleUser(string $ago, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    $user->forceFill(['created_at' => now()->sub($ago)])->saveQuietly();

    return $user->fresh();
}

function succeededRunFor(User $user): Run
{
    $workflow = Workflow::factory()->forWorkspace($user->currentWorkspace)->create();

    return Run::factory()->forWorkflow($workflow)->completed()->create(['triggered_by' => $user->id]);
}

function sentLifecycleEmails(User $user): array
{
    $sent = [];

    Notification::assertSentTo($user, LifecycleEmailNotification::class, function (LifecycleEmailNotification $notification) use (&$sent): bool {
        $sent[] = $notification->email;

        return true;
    });

    return $sent;
}

it('welcomes a new user once', function () {
    $user = lifecycleUser('5 minutes');

    $this->artisan('onboarding:send-lifecycle-emails')->assertSuccessful();
    $this->artisan('onboarding:send-lifecycle-emails')->assertSuccessful();

    Notification::assertSentToTimes($user, LifecycleEmailNotification::class, 1);
    expect(sentLifecycleEmails($user))->toBe([LifecycleEmail::Welcome]);
});

it('nudges someone without a first run after an hour, but not someone who has run a workflow', function () {
    $idle = lifecycleUser('2 hours');
    $active = lifecycleUser('2 hours');
    succeededRunFor($active);
    foreach ([$idle, $active] as $user) {
        LifecycleEmailSend::query()->create(['user_id' => $user->id, 'email' => LifecycleEmail::Welcome, 'sent_at' => now()->subHours(2)]);
    }

    $this->artisan('onboarding:send-lifecycle-emails')->assertSuccessful();

    expect(sentLifecycleEmails($idle))->toBe([LifecycleEmail::FirstRunNudge]);
    Notification::assertNotSentTo($active, LifecycleEmailNotification::class);
});

it('asks someone whose workflow worked to turn on a trigger, unless one is on', function () {
    $withoutTrigger = lifecycleUser('2 days 2 hours');
    $withTrigger = lifecycleUser('2 days 2 hours');
    succeededRunFor($withoutTrigger);
    Trigger::factory()->forWorkflow(succeededRunFor($withTrigger)->workflow)->create(['is_active' => true]);

    $this->artisan('onboarding:send-lifecycle-emails')->assertSuccessful();

    expect(sentLifecycleEmails($withoutTrigger))->toBe([LifecycleEmail::AutomateIt]);
    Notification::assertNotSentTo($withTrigger, LifecycleEmailNotification::class);
});

it('sends at most one capped email a day', function () {
    $user = lifecycleUser('3 days 1 hour');
    LifecycleEmailSend::query()->create(['user_id' => $user->id, 'email' => LifecycleEmail::AutomateIt, 'sent_at' => now()->subHours(3)]);

    $this->artisan('onboarding:send-lifecycle-emails')->assertSuccessful();

    Notification::assertNotSentTo($user, LifecycleEmailNotification::class);
});

it('sends nothing to an account whose emails all came due long ago', function () {
    $user = lifecycleUser('10 days');

    $this->artisan('onboarding:send-lifecycle-emails')->assertSuccessful();

    Notification::assertNotSentTo($user, LifecycleEmailNotification::class);
});

it('recaps a first week that had runs, and stays quiet about one that had none', function () {
    $busy = lifecycleUser('7 days 1 hour');
    $quiet = lifecycleUser('7 days 1 hour');
    succeededRunFor($busy);
    succeededRunFor($busy);

    $this->artisan('onboarding:send-lifecycle-emails')->assertSuccessful();

    Notification::assertSentTo($busy, fn (LifecycleEmailNotification $notification) => $notification->email === LifecycleEmail::FirstWeekRecap
        && $notification->details['runs_this_week'] === 2
        && $notification->toMail($busy)->subject === 'Your first week: your workflows ran 2 times');
    Notification::assertNotSentTo($quiet, LifecycleEmailNotification::class);
});

it('wins back someone whose workflows stopped running', function () {
    $user = lifecycleUser('21 days 1 hour');

    $this->artisan('onboarding:send-lifecycle-emails')->assertSuccessful();

    expect(sentLifecycleEmails($user))->toBe([LifecycleEmail::WinBack]);
});

it('leaves alone users who opted out, are suspended or have not verified their email', function (array $attributes) {
    $user = lifecycleUser('2 hours', $attributes);
    LifecycleEmailSend::query()->create(['user_id' => $user->id, 'email' => LifecycleEmail::Welcome, 'sent_at' => now()->subHours(2)]);

    $this->artisan('onboarding:send-lifecycle-emails')->assertSuccessful();

    Notification::assertNotSentTo($user, LifecycleEmailNotification::class);
})->with([
    'opted out of marketing' => [['marketing_opted_out_at' => now()]],
    'suspended' => [['suspended_at' => now()]],
    'unverified' => [['email_verified_at' => null]],
]);

it('carries an unsubscribe link and one-click unsubscribe headers', function () {
    $user = lifecycleUser('5 minutes');
    $mail = (new LifecycleEmailNotification(LifecycleEmail::Welcome, ['workspace_id' => $user->current_workspace_id]))->toMail($user);

    expect($mail->salutation)->toContain('Stop these emails')
        ->and($mail->callbacks)->toHaveCount(1);
});
