<?php

use App\Models\Billing\Subscription;
use App\Models\User;
use App\Notifications\Billing\TrialEndingNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;

function trialingWorkspace(string $stripeId, $trialEndsAt, ?string $notifiedAt = null): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $subscription = Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => $stripeId,
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_test',
        'trial_ends_at' => $trialEndsAt,
        'trial_ending_notified_at' => $notifiedAt,
    ]);

    return [$workspace, $subscription];
}

it('notifies a workspace whose trial ends inside the warning window', function () {
    Notification::fake();
    [$workspace] = trialingWorkspace('sub_soon', now()->addDays(2));

    $this->artisan('billing:notify-trial-ending')
        ->expectsOutputToContain('Notified 1 workspace(s)')
        ->assertSuccessful();

    Notification::assertSentTo($workspace->owner, TrialEndingNotification::class);
});

it('marks the subscription notified so it is not told twice', function () {
    Notification::fake();
    [, $subscription] = trialingWorkspace('sub_soon', now()->addDays(2));

    $this->artisan('billing:notify-trial-ending')->assertSuccessful();

    expect($subscription->fresh()->trial_ending_notified_at)->not->toBeNull();

    Notification::fake();
    $this->artisan('billing:notify-trial-ending')
        ->expectsOutputToContain('Notified 0 workspace(s)')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

it('leaves a trial outside the warning window untouched', function () {
    Notification::fake();
    trialingWorkspace('sub_far', now()->addDays(10));

    $this->artisan('billing:notify-trial-ending')
        ->expectsOutputToContain('Notified 0 workspace(s)')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not re-notify a trial that was already announced', function () {
    Notification::fake();
    trialingWorkspace('sub_seen', now()->addDays(1), notifiedAt: now()->subHour());

    $this->artisan('billing:notify-trial-ending')
        ->expectsOutputToContain('Notified 0 workspace(s)')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

it('ignores a subscription that is no longer trialing', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => 'sub_active',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'trial_ends_at' => now()->addDay(),
    ]);

    $this->artisan('billing:notify-trial-ending')
        ->expectsOutputToContain('Notified 0 workspace(s)')
        ->assertSuccessful();

    Notification::assertNothingSent();
});
