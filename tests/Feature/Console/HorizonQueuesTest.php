<?php

use App\Enums\Queue;

it('has a horizon supervisor consuming every queue jobs are dispatched to', function () {
    $consumed = collect(config('horizon.defaults'))->pluck('queue')->flatten()->unique();

    $dispatched = collect(Queue::cases())->map(fn (Queue $queue): string => $queue->value)
        ->merge([config('triggers.poll_queue'), config('triggers.fire_queue'), config('triggers.agent_fire_queue')]);

    expect($dispatched->diff($consumed)->values()->all())->toBe([]);
});
