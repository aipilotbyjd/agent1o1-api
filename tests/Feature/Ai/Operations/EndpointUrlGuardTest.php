<?php

use App\Services\Ai\EndpointUrlGuard;

/**
 * A guard whose DNS answers are scripted, so no test depends on the network.
 *
 * @param  array<string, array<int, string>>  $dns
 */
function guardWithDns(array $dns = []): EndpointUrlGuard
{
    return new class($dns) extends EndpointUrlGuard
    {
        /** @param array<string, array<int, string>> $dns */
        public function __construct(private array $dns) {}

        protected function resolve(string $host): array|false
        {
            return $this->dns[$host] ?? false;
        }
    };
}

it('accepts an https URL on a public address', function (string $url) {
    expect(guardWithDns(['llm.example.com' => ['93.184.216.34']])->problem($url))->toBeNull();
})->with([
    'a public IP' => ['https://93.184.216.34/v1'],
    'a host that resolves publicly' => ['https://llm.example.com/v1'],
    'with a port' => ['https://llm.example.com:8443/v1'],
]);

it('refuses a URL that points inside', function (string $url) {
    expect(guardWithDns(['internal.example.com' => ['10.0.0.9']])->problem($url))->toContain('private or reserved');
})->with([
    'loopback' => ['https://127.0.0.1/v1'],
    'a private range' => ['https://10.0.0.5/v1'],
    'another private range' => ['https://192.168.1.10/v1'],
    'the cloud metadata address' => ['https://169.254.169.254/latest'],
    'ipv6 loopback' => ['https://[::1]/v1'],
    'a name that resolves to a private address' => ['https://internal.example.com/v1'],
]);

it('refuses a host that does not resolve', function () {
    expect(guardWithDns()->problem('https://no-such-host.example.com/v1'))->toContain('does not resolve');
});

it('refuses plain http outside local and testing', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(guardWithDns()->problem('http://93.184.216.34/v1'))->toBe('The URL must use https.');
});

it('allows plain http while developing', function () {
    expect(guardWithDns()->problem('http://93.184.216.34/v1'))->toBeNull();
});

it('refuses URLs that are not URLs, other schemes, and credentials in the URL', function (string $url) {
    expect(guardWithDns()->problem($url))->not->toBeNull();
})->with([
    'no scheme' => ['llm.example.com/v1'],
    'not a URL' => ['not a url'],
    'ftp' => ['ftp://93.184.216.34/v1'],
    'file' => ['file:///etc/passwd'],
    'userinfo' => ['https://user:pass@93.184.216.34/v1'],
]);

it('lets a named private host through, over http too, and nothing else', function () {
    config(['super_admin_security.endpoints.private_hosts' => ['vllm.internal', '10.0.0.5']]);
    app()->detectEnvironment(fn () => 'production');

    $guard = guardWithDns();

    expect($guard->problem('http://vllm.internal:8000/v1'))->toBeNull()
        ->and($guard->problem('https://VLLM.internal/v1'))->toBeNull()
        ->and($guard->problem('https://10.0.0.5/v1'))->toBeNull()
        ->and($guard->problem('https://10.0.0.6/v1'))->toContain('private or reserved');
});
