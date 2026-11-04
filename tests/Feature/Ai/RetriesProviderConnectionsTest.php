<?php

use App\Ai\Support\RetriesProviderConnections;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

function providerClient(array $queue): array
{
    $mock = new MockHandler($queue);
    $stack = HandlerStack::create($mock);
    $stack->push(RetriesProviderConnections::middleware());

    return [new Client(['handler' => $stack]), $mock];
}

it('retries a dropped connection to an AI provider and then succeeds', function () {
    [$client, $mock] = providerClient([
        new ConnectException('Connection reset', new Request('POST', 'https://vyceai.com/v1/chat/completions')),
        new Response(200, [], 'ok'),
    ]);

    config(['ai.providers.vyce.url' => 'https://vyceai.com/v1']);

    expect((string) $client->post('https://vyceai.com/v1/chat/completions')->getBody())->toBe('ok');
    expect($mock->count())->toBe(0);
});

it('gives up after the retry limit', function () {
    config(['ai.providers.vyce.url' => 'https://vyceai.com/v1']);
    $request = new Request('POST', 'https://vyceai.com/v1/chat/completions');

    expect(RetriesProviderConnections::shouldRetry(0, $request, new ConnectException('x', $request)))->toBeTrue();
    expect(RetriesProviderConnections::shouldRetry(1, $request, new ConnectException('x', $request)))->toBeTrue();
    expect(RetriesProviderConnections::shouldRetry(RetriesProviderConnections::MAX_RETRIES, $request, new ConnectException('x', $request)))->toBeFalse();
});

it('does not retry requests to hosts that are not AI providers', function () {
    $request = new Request('POST', 'https://hooks.example.com/webhook');

    expect(RetriesProviderConnections::shouldRetry(0, $request, new ConnectException('x', $request)))->toBeFalse();
});

it('does not retry when the provider answered, even with an error', function () {
    config(['ai.providers.vyce.url' => 'https://vyceai.com/v1']);
    $request = new Request('POST', 'https://vyceai.com/v1/chat/completions');

    expect(RetriesProviderConnections::shouldRetry(0, $request, null))->toBeFalse();
    expect(RetriesProviderConnections::shouldRetry(0, $request, new RuntimeException('x')))->toBeFalse();
});

it('backs off a little longer each time', function () {
    expect(RetriesProviderConnections::delayMilliseconds(1))->toBe(500);
    expect(RetriesProviderConnections::delayMilliseconds(2))->toBe(1500);
});
