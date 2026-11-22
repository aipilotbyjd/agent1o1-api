<?php

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Psr\Http\Message\RequestInterface;

/**
 * Rebuilds the schedule from routes/console.php with the given Healthchecks URL and
 * returns the heartbeat event, plus the requests the scheduler's HTTP client received.
 *
 * @return array{0: Event, 1: ArrayObject<int, array{request: RequestInterface}>}
 */
function heartbeatEventWith(?string $url): array
{
    config()->set('services.healthchecks.scheduler_url', $url);

    $sent = new ArrayObject;
    $stack = HandlerStack::create(new MockHandler([new Response(200)]));
    $stack->push(Middleware::history($sent));
    app()->instance(ClientInterface::class, new Client(['handler' => $stack]));

    app()->instance(Schedule::class, new Schedule);
    ScheduleFacade::clearResolvedInstance(Schedule::class);
    require base_path('routes/console.php');

    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'system:heartbeat'));

    return [$event, $sent];
}

test('the scheduler pings Healthchecks.io after the heartbeat succeeds', function () {
    [$event, $sent] = heartbeatEventWith('https://hc-ping.com/test-uuid');

    $event->finish(app(), 0);

    expect($sent)->toHaveCount(1)
        ->and((string) $sent[0]['request']->getUri())->toBe('https://hc-ping.com/test-uuid')
        ->and($sent[0]['request']->getMethod())->toBe('GET');
});

test('a failed heartbeat does not ping, so Healthchecks.io raises the alarm', function () {
    [$event, $sent] = heartbeatEventWith('https://hc-ping.com/test-uuid');

    $event->finish(app(), 1);

    expect($sent)->toHaveCount(0);
});

test('nothing is pinged while the URL is empty', function () {
    [$event, $sent] = heartbeatEventWith(null);

    $event->finish(app(), 0);

    expect($sent)->toHaveCount(0);
});
