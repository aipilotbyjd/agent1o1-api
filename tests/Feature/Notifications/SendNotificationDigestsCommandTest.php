<?php

use App\Models\Notifications\NotificationDigestItem;
use App\Models\User;
use App\Notifications\Account\NotificationDigestNotification;
use Illuminate\Support\Facades\Notification;

it('sends each member one digest of their held-back emails and clears them', function () {
    Notification::fake();
    $member = User::factory()->create();
    $other = User::factory()->create();

    foreach (['First', 'Second'] as $minutesAgo => $title) {
        NotificationDigestItem::query()->create(['user_id' => $member->id, 'event_key' => 'run.failed', 'title' => $title, 'created_at' => now()->subMinutes(10 - $minutesAgo)]);
    }
    NotificationDigestItem::query()->create(['user_id' => $other->id, 'event_key' => 'run.failed', 'title' => 'Third', 'created_at' => now()]);

    $this->artisan('notifications:send-digests')->assertSuccessful();

    Notification::assertSentToTimes($member, NotificationDigestNotification::class, 1);
    Notification::assertSentTo($member, NotificationDigestNotification::class, fn (NotificationDigestNotification $digest): bool => array_column($digest->items, 'title') === ['First', 'Second']);
    Notification::assertSentToTimes($other, NotificationDigestNotification::class, 1);
    expect(NotificationDigestItem::query()->count())->toBe(0);
});

it('caps a digest and says how many more are waiting in the app', function () {
    Notification::fake();
    config(['notifications.digest.max_items' => 1]);
    $member = User::factory()->create();

    foreach (['First', 'Second', 'Third'] as $title) {
        NotificationDigestItem::query()->create(['user_id' => $member->id, 'event_key' => 'run.failed', 'title' => $title, 'created_at' => now()]);
    }

    $this->artisan('notifications:send-digests')->assertSuccessful();

    Notification::assertSentTo($member, NotificationDigestNotification::class, fn (NotificationDigestNotification $digest): bool => count($digest->items) === 1 && $digest->omitted === 2);
});

it('sends nothing when no emails are waiting', function () {
    Notification::fake();

    $this->artisan('notifications:send-digests')->assertSuccessful();

    Notification::assertNothingSent();
});
