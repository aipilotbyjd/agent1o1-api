<?php

use App\Engine\Trigger\NormalizerRegistry;
use App\Engine\Trigger\Normalizers\GitHubNormalizer;
use App\Engine\Trigger\Normalizers\StripeNormalizer;

test('github push payload is normalized into a consistent shape', function () {
    $normalized = (new GitHubNormalizer)->normalize([
        'ref' => 'refs/heads/main',
        'commits' => [
            ['id' => 'abc', 'message' => 'fix', 'author' => ['name' => 'Jane'], 'url' => 'http://x'],
        ],
        'repository' => ['full_name' => 'acme/app', 'private' => true, 'html_url' => 'http://r'],
    ], 'push');

    expect($normalized['trigger'])->toBe(['provider' => 'github', 'event' => 'push'])
        ->and($normalized['data']['ref'])->toBe('refs/heads/main')
        ->and($normalized['data']['commits'][0]['author'])->toBe('Jane')
        ->and($normalized['data']['repository']['full_name'])->toBe('acme/app');
});

test('github dedup key comes from the delivery header', function () {
    $key = (new GitHubNormalizer)->extractDedupKey([], ['X-GitHub-Delivery' => 'delivery-123']);

    expect($key)->toBe('delivery-123');
});

test('stripe payload extracts event metadata and dedups on event id', function () {
    $raw = [
        'id' => 'evt_42',
        'type' => 'charge.succeeded',
        'data' => ['object' => ['object' => 'charge', 'id' => 'ch_1']],
    ];

    $normalized = (new StripeNormalizer)->normalize($raw, 'charge.succeeded');

    expect($normalized['data']['event_type'])->toBe('charge.succeeded')
        ->and($normalized['data']['object_id'])->toBe('ch_1')
        ->and((new StripeNormalizer)->extractDedupKey($raw))->toBe('evt_42');
});

test('the normalizer registry falls back to the generic normalizer', function () {
    $registry = new NormalizerRegistry;

    expect($registry->resolve('github')->provider())->toBe('github')
        ->and($registry->resolve('does-not-exist')->provider())->toBe('generic');
});
