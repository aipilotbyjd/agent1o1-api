<?php

use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Triggers\Trigger;
use App\Services\Http\SsrfGuard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    app()->instance(SsrfGuard::class, new SsrfGuard(fn (string $host) => $host === 'feed.example.com' ? ['93.184.216.34'] : []));
});

it('polls a public endpoint', function () {
    Http::fake(['https://feed.example.com/*' => Http::response(['items' => []])]);

    $trigger = Trigger::factory()->create([
        'type' => TriggerType::Polling,
        'config' => ['url' => 'https://feed.example.com/items', 'items_path' => 'items'],
    ]);

    dispatch_sync(new PollTrigger($trigger));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://feed.example.com/items');
    expect($trigger->fresh()->consecutive_failure_count)->toBe(0);
});

it('records a failure instead of polling an internal address', function (string $url) {
    Http::fake();

    $trigger = Trigger::factory()->create([
        'type' => TriggerType::Polling,
        'config' => ['url' => $url],
    ]);

    dispatch_sync(new PollTrigger($trigger));

    Http::assertNothingSent();
    expect($trigger->fresh()->consecutive_failure_count)->toBe(1);
})->with([
    'metadata' => 'http://169.254.169.254/latest/meta-data/',
    'loopback' => 'http://127.0.0.1:8080/',
    'private dns' => 'https://rebind.example.com/',
]);
