<?php

use App\Exceptions\Http\BlockedUrlException;
use App\Services\Http\SsrfGuard;

$publicResolver = fn () => ['93.184.216.34'];

it('allows a public http and https URL', function (string $url) use ($publicResolver) {
    (new SsrfGuard($publicResolver))->assertUrlIsAllowed($url);

    expect(true)->toBeTrue();
})->with([
    'https' => 'https://api.example.com/v1/status',
    'http with a port' => 'http://api.example.com:8080/hook',
    'public ipv4 literal' => 'https://93.184.216.34/',
    'public ipv6 literal' => 'https://[2606:2800:220:1:248:1893:25c8:1946]/',
]);

it('blocks non-public IP literals', function (string $url) {
    (new SsrfGuard)->assertUrlIsAllowed($url);
})->with([
    'loopback' => 'http://127.0.0.1/',
    'loopback range' => 'http://127.8.8.8/',
    'unspecified' => 'http://0.0.0.0/',
    'this-network' => 'http://0.1.2.3/',
    'rfc1918 10/8' => 'http://10.0.0.5/',
    'rfc1918 172.16/12' => 'http://172.31.255.255/',
    'rfc1918 192.168/16' => 'http://192.168.1.1/',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'link-local' => 'http://169.254.10.10/',
    'carrier-grade nat' => 'http://100.64.0.1/',
    'benchmarking' => 'http://198.18.0.1/',
    'multicast' => 'http://224.0.0.1/',
    'broadcast' => 'http://255.255.255.255/',
    'ipv6 loopback' => 'http://[::1]/',
    'ipv6 unspecified' => 'http://[::]/',
    'ipv6 unique-local' => 'http://[fd00::1]/',
    'ipv6 link-local' => 'http://[fe80::1]/',
    'ipv4-mapped loopback' => 'http://[::ffff:127.0.0.1]/',
    'ipv4-mapped metadata' => 'http://[::ffff:169.254.169.254]/',
    'ipv4-mapped hex' => 'http://[::ffff:7f00:1]/',
    'nat64 loopback' => 'http://[64:ff9b::7f00:1]/',
    '6to4 loopback' => 'http://[2002:7f00:1::]/',
    'ipv4-compatible loopback' => 'http://[::127.0.0.1]/',
])->throws(BlockedUrlException::class);

it('blocks ambiguous spellings of an IPv4 address', function (string $url) {
    (new SsrfGuard(fn () => ['93.184.216.34']))->assertUrlIsAllowed($url);
})->with([
    'decimal' => 'http://2130706433/',
    'octal' => 'http://0177.0.0.1/',
    'hex' => 'http://0x7f.0.0.1/',
    'short' => 'http://127.1/',
])->throws(BlockedUrlException::class);

it('blocks internal host names and credentials in the URL', function (string $url) {
    (new SsrfGuard(fn () => ['93.184.216.34']))->assertUrlIsAllowed($url);
})->with([
    'localhost' => 'http://localhost/',
    'localhost subdomain' => 'http://app.localhost/',
    'internal tld' => 'http://metadata.google.internal/',
    'mdns' => 'http://printer.local/',
    'userinfo' => 'http://user:pass@api.example.com/',
    'userinfo disguise' => 'http://api.example.com@10.0.0.1/',
])->throws(BlockedUrlException::class);

it('blocks schemes other than http and https', function (string $url) {
    (new SsrfGuard(fn () => ['93.184.216.34']))->assertUrlIsAllowed($url);
})->with([
    'file' => 'file:///etc/passwd',
    'gopher' => 'gopher://example.com/',
    'ftp' => 'ftp://example.com/',
    'dict' => 'dict://example.com:11211/',
    'scheme-less' => '//example.com/',
    'garbage' => 'not a url',
])->throws(BlockedUrlException::class);

it('blocks a host when any resolved address is non-public', function () {
    $guard = new SsrfGuard(fn () => ['93.184.216.34', '10.0.0.5']);

    $guard->assertUrlIsAllowed('https://dual.example.com/');
})->throws(BlockedUrlException::class);

it('blocks a host that resolves only to a private IPv6 address', function () {
    (new SsrfGuard(fn () => ['fd12:3456::1']))->assertUrlIsAllowed('https://v6.example.com/');
})->throws(BlockedUrlException::class);

it('blocks a host that does not resolve', function () {
    (new SsrfGuard(fn () => []))->assertUrlIsAllowed('https://nxdomain.example.com/');
})->throws(BlockedUrlException::class);

it('returns the validated addresses so the connection can be pinned', function () {
    $guard = new SsrfGuard(fn () => ['93.184.216.34']);

    expect($guard->resolve('https://api.example.com/x'))->toBe([
        'host' => 'api.example.com',
        'port' => 443,
        'ips' => ['93.184.216.34'],
        'pinnable' => true,
    ])->and($guard->resolve('http://api.example.com:8080/x')['port'])->toBe(8080)
        ->and($guard->resolve('https://93.184.216.34/')['pinnable'])->toBeFalse();
});

it('does not touch DNS for the syntax-only check', function () {
    $guard = new SsrfGuard(fn () => throw new RuntimeException('resolver must not be called'));

    $guard->assertUrlSyntaxIsAllowed('https://api.example.com/hook');

    expect(fn () => $guard->assertUrlSyntaxIsAllowed('http://10.0.0.1/'))->toThrow(BlockedUrlException::class);
});
