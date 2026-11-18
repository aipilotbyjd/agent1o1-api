<?php

use App\Enums\Onboarding\OnboardingStep;
use App\Models\User;
use App\Notifications\Onboarding\FinishOnboardingReminderNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

/**
 * A user who signed up two days ago and last moved forward over a day ago.
 *
 * @param  array<string, mixed>  $attributes
 */
function idleOnboardingUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    $user->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subHours(25)])->saveQuietly();

    return $user;
}

it('emails a user who left onboarding a day ago, once', function () {
    $user = idleOnboardingUser();

    $this->artisan('onboarding:send-reminders')->assertSuccessful();
    $this->artisan('onboarding:send-reminders')->assertSuccessful();

    Notification::assertSentToTimes($user, FinishOnboardingReminderNotification::class, 1);
    expect($user->fresh()->onboarding_reminder_sent_at)->not->toBeNull();
});

it('counts the steps still standing before a first automation', function () {
    $fresh = idleOnboardingUser();

    $pastTemplate = User::factory()->create();
    app(WorkspaceService::class)->create($pastTemplate, ['name' => 'Acme']);
    $pastTemplate->forceFill([
        'onboarding_current_step' => OnboardingStep::FirstRun,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subHours(25),
    ])->saveQuietly();

    $this->artisan('onboarding:send-reminders')->assertSuccessful();

    Notification::assertSentTo($fresh, fn (FinishOnboardingReminderNotification $notification) => $notification->stepsLeft === 3);
    Notification::assertSentTo($pastTemplate, function (FinishOnboardingReminderNotification $notification) use ($pastTemplate) {
        return $notification->stepsLeft === 2
            && $notification->toMail($pastTemplate)->subject === "You're 2 steps from your first automation";
    });
});

it('leaves alone users who are not idle mid-onboarding', function (array $attributes, string $updatedAgo, string $createdAgo) {
    $user = User::factory()->create($attributes);
    $user->forceFill([
        'created_at' => now()->sub($createdAgo),
        'updated_at' => now()->sub($updatedAgo),
    ])->saveQuietly();

    $this->artisan('onboarding:send-reminders')->assertSuccessful();

    Notification::assertNothingSentTo($user);
})->with([
    'still active' => [[], '2 hours', '2 days'],
    'signed up over a week ago' => [[], '25 hours', '8 days'],
    'finished' => [['onboarding_completed_at' => now()], '25 hours', '2 days'],
    'closed the wizard' => [['onboarding_dismissed_at' => now()], '25 hours', '2 days'],
    'opted out of marketing' => [['marketing_opted_out_at' => now()], '25 hours', '2 days'],
    'unverified' => [['email_verified_at' => null], '25 hours', '2 days'],
]);
