<?php

use App\Exceptions\Http\BlockedUrlException;
use App\Services\Http\GuardedHttp;
use App\Services\Http\SsrfGuard;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function guardedHttp(?Closure $resolver = null): GuardedHttp
{
    return new GuardedHttp(new SsrfGuard($resolver ?? fn (string $host) => match ($host) {
        'api.example.com', 'other.example.com' => ['93.184.216.34'],
        default => [],
    }));
}

it('returns the response of an allowed request', function () {
    Http::fake(['https://api.example.com/*' => Http::response(['ok' => true])]);

    $response = guardedHttp()->send('POST', 'https://api.example.com/hook', ['X-Token' => 'secret'], ['a' => 1]);

    expect($response->json('ok'))->toBeTrue();
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['a'] === 1
        && $request->header('X-Token') === ['secret']);
});

it('sends no body when none is given and merges the query into the first request', function () {
    Http::fake(['https://api.example.com/*' => Http::response([])]);

    guardedHttp()->send('GET', 'https://api.example.com/items', query: ['since' => '42']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.example.com/items?since=42' && $request->body() === '');
});

it('blocks a request to a non-public address before sending anything', function () {
    Http::fake();

    expect(fn () => guardedHttp()->send('GET', 'http://169.254.169.254/latest/meta-data/'))
        ->toThrow(BlockedUrlException::class);

    Http::assertNothingSent();
});

it('follows a redirect to another public host', function () {
    Http::fake([
        'https://api.example.com/old' => Http::response('', 302, ['Location' => 'https://other.example.com/new']),
        'https://other.example.com/new' => Http::response(['moved' => true]),
    ]);

    expect(guardedHttp()->send('GET', 'https://api.example.com/old')->json('moved'))->toBeTrue();
});

it('blocks a redirect to a private address and never connects to it', function (string $location) {
    Http::fake([
        'https://api.example.com/*' => Http::response('', 302, ['Location' => $location]),
        '*' => Http::response('internal'),
    ]);

    expect(fn () => guardedHttp()->send('GET', 'https://api.example.com/redirect'))
        ->toThrow(BlockedUrlException::class);

    Http::assertSentCount(1);
})->with([
    'absolute loopback' => 'http://127.0.0.1/admin',
    'metadata' => 'http://169.254.169.254/latest/meta-data/',
    'scheme-relative' => '//127.0.0.1/admin',
    'unresolvable host' => 'https://nowhere.example.net/',
    'wrong scheme' => 'file:///etc/passwd',
]);

it('gives up after too many redirects', function () {
    Http::fake(['https://api.example.com/*' => Http::response('', 302, ['Location' => '/again'])]);

    expect(fn () => guardedHttp()->send('GET', 'https://api.example.com/loop'))
        ->toThrow(BlockedUrlException::class, 'too many redirects');

    Http::assertSentCount(GuardedHttp::MAX_REDIRECTS + 1);
});

it('resolves relative redirect locations', function (string $location, string $expected) {
    Http::fake([
        'https://api.example.com/a/b' => Http::response('', 302, ['Location' => $location]),
        '*' => Http::response('done'),
    ]);

    guardedHttp()->send('GET', 'https://api.example.com/a/b');

    Http::assertSent(fn (Request $request) => $request->url() === $expected);
})->with([
    'absolute path' => ['/x/y', 'https://api.example.com/x/y'],
    'relative path' => ['c', 'https://api.example.com/a/c'],
]);

it('drops credentials and custom headers when a redirect leaves the origin', function () {
    Http::fake([
        'https://api.example.com/*' => Http::response('', 302, ['Location' => 'https://other.example.com/steal']),
        'https://other.example.com/*' => Http::response('ok'),
    ]);

    guardedHttp()->send('GET', 'https://api.example.com/start', [
        'Authorization' => 'Bearer secret',
        'X-Api-Key' => 'secret',
        'Accept' => 'application/json',
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://other.example.com/steal'
        && ! $request->hasHeader('Authorization')
        && ! $request->hasHeader('X-Api-Key')
        && $request->hasHeader('Accept'));
});

it('keeps headers when a redirect stays on the same origin', function () {
    Http::fake([
        'https://api.example.com/start' => Http::response('', 302, ['Location' => '/next']),
        'https://api.example.com/next' => Http::response('ok'),
    ]);

    guardedHttp()->send('GET', 'https://api.example.com/start', ['Authorization' => 'Bearer secret']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.example.com/next'
        && $request->header('Authorization') === ['Bearer secret']);
});

it('turns a redirected POST into a body-less GET, but keeps the method on 307', function () {
    Http::fake([
        'https://api.example.com/post' => Http::response('', 302, ['Location' => '/get']),
        'https://api.example.com/get' => Http::response('ok'),
        'https://api.example.com/keep' => Http::response('', 307, ['Location' => '/kept']),
        'https://api.example.com/kept' => Http::response('ok'),
    ]);

    guardedHttp()->send('POST', 'https://api.example.com/post', json: ['secret' => 1]);
    guardedHttp()->send('POST', 'https://api.example.com/keep', json: ['secret' => 1]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.example.com/get' && $request->method() === 'GET' && $request->body() === '');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.example.com/kept' && $request->method() === 'POST' && $request['secret'] === 1);
});

it('pins the connection to the addresses it validated', function () {
    $seen = null;

    Http::fake(function (Request $request, array $options) use (&$seen) {
        $seen = $options;

        return Http::response('ok');
    });

    guardedHttp()->send('GET', 'https://api.example.com:8443/x');

    expect($seen['allow_redirects'])->toBeFalse()
        ->and($seen['curl'][CURLOPT_RESOLVE])->toBe(['api.example.com:8443:93.184.216.34']);
});

it('does not pin when the host is already an IP literal', function () {
    $seen = null;

    Http::fake(function (Request $request, array $options) use (&$seen) {
        $seen = $options;

        return Http::response('ok');
    });

    guardedHttp()->send('GET', 'https://93.184.216.34/x');

    expect($seen)->not->toHaveKey('curl');
});

it('caps the response size', function () {
    $seen = null;

    Http::fake(function (Request $request, array $options) use (&$seen) {
        $seen = $options;

        return Http::response('ok');
    });

    guardedHttp()->send('GET', 'https://api.example.com/x');

    $tooBig = GuardedHttp::MAX_RESPONSE_BYTES + 1;

    expect(fn () => $seen['progress'](0, $tooBig))->toThrow(BlockedUrlException::class)
        ->and(fn () => $seen['on_headers'](new Response(200, ['Content-Length' => (string) $tooBig])))->toThrow(BlockedUrlException::class);

    $seen['progress'](0, 1024);
    $seen['on_headers'](new Response(200, ['Content-Length' => '1024']));
});

it('retries a failing request up to the attempt limit and returns the last response', function () {
    Http::fake(['https://api.example.com/*' => Http::sequence()->push('', 500)->push('', 503)->push('', 200)]);

    $response = guardedHttp()->send('POST', 'https://api.example.com/hook', json: [], retries: 2);

    expect($response->status())->toBe(503);
    Http::assertSentCount(2);
});
