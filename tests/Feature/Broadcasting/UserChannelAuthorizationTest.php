<?php

use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;

function userChannelCallback(): Closure
{
    return app(BroadcastManager::class)->driver()->getChannels()->get('App.Models.User.{id}');
}

it('lets a user subscribe to their own channel', function () {
    $user = User::factory()->create();

    expect(userChannelCallback()($user, (string) $user->id))->toBeTrue();
});

it('refuses another user channel even when the ids share leading digits', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    // Both are UUIDv7s starting with the same digits: the old (int) cast
    // turned both into the same number and let this through.
    expect((int) $user->id)->toBe((int) $other->id)
        ->and(userChannelCallback()($user, (string) $other->id))->toBeFalse();
});
