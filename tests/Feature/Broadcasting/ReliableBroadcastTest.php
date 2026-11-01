<?php

use App\Broadcasting\BroadcastsReliably;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Queue;

function reliableEvent(): ShouldBroadcast
{
    return new class implements ShouldBroadcast
    {
        use BroadcastsReliably;

        public function broadcastOn(): array
        {
            return [];
        }
    };
}

it('hands the retry, backoff and commit settings to the queued broadcast job', function () {
    $job = new BroadcastEvent(reliableEvent());

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([1, 5])
        ->and($job->afterCommit)->toBeTrue();
});

it('queues the broadcast on its own queue', function () {
    Queue::fake();

    Broadcast::queue(reliableEvent());

    Queue::assertPushedOn('broadcasts', BroadcastEvent::class);
});

it('has every queued broadcast event retry safely', function () {
    $unreliable = collect(glob(app_path('Events/*/*.php')))
        ->map(fn (string $file): string => 'App\\Events\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(app_path('Events/')))))
        ->filter(fn (string $class): bool => is_subclass_of($class, ShouldBroadcast::class)
            && ! is_subclass_of($class, ShouldBroadcastNow::class))
        ->reject(fn (string $class): bool => in_array(BroadcastsReliably::class, class_uses_recursive($class), true))
        ->values()
        ->all();

    expect($unreliable)->toBe([]);
});
