<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/api/_client-probe', fn (Request $request) => ['ip' => $request->ip()]);
});

function clientIpSeenBehindProxy(string $proxyAddress): string
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => $proxyAddress])
        ->getJson('/api/_client-probe', ['X-Forwarded-For' => '203.0.113.7'])
        ->assertOk()
        ->json('ip');
}

it('reports the proxy as the client when no proxy is trusted', function () {
    config(['trustedproxy.proxies' => null]);

    expect(clientIpSeenBehindProxy('10.0.0.5'))->toBe('10.0.0.5');
});

it('reports the forwarded client address when the caller is in a trusted range', function () {
    config(['trustedproxy.proxies' => '10.0.0.0/8,172.16.0.0/12']);

    expect(clientIpSeenBehindProxy('10.0.0.5'))->toBe('203.0.113.7');
});

it('ignores forwarded headers from a caller outside the trusted ranges', function () {
    config(['trustedproxy.proxies' => '10.0.0.0/8']);

    expect(clientIpSeenBehindProxy('198.51.100.20'))->toBe('198.51.100.20');
});

it('gives each forwarded client its own login rate-limit bucket behind a trusted proxy', function () {
    config(['trustedproxy.proxies' => '10.0.0.0/8']);

    $attempt = fn (string $clientIp, int $n) => test()
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->postJson('/api/v1/auth/login', ['email' => "nobody{$n}@example.com", 'password' => 'wrong'], ['X-Forwarded-For' => $clientIp]);

    foreach (range(1, 10) as $n) {
        $attempt('203.0.113.7', $n);
    }

    $attempt('203.0.113.7', 11)->assertTooManyRequests();
    expect($attempt('203.0.113.8', 12)->status())->not->toBe(429);
});

it('shares one login rate-limit bucket across all clients when no proxy is trusted', function () {
    config(['trustedproxy.proxies' => null]);

    $attempt = fn (string $clientIp, int $n) => test()
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->postJson('/api/v1/auth/login', ['email' => "nobody{$n}@example.com", 'password' => 'wrong'], ['X-Forwarded-For' => $clientIp]);

    foreach (range(1, 10) as $n) {
        $attempt("203.0.113.{$n}", $n);
    }

    $attempt('203.0.113.99', 11)->assertTooManyRequests();
});
