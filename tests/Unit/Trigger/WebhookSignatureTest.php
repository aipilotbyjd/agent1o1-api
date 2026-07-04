<?php

use App\Engine\Webhook\GitHubRegistrar;
use App\Engine\Webhook\SlackRegistrar;
use App\Engine\Webhook\StripeRegistrar;
use App\Engine\Webhook\WebhookRegistrarRegistry;

test('github verifies a valid sha256 signature and rejects a bad one', function () {
    $registrar = new GitHubRegistrar;
    $secret = 'topsecret';
    $payload = json_encode(['ref' => 'refs/heads/main']);
    $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

    expect($registrar->verifySignature($payload, $signature, $secret))->toBeTrue()
        ->and($registrar->verifySignature($payload, 'sha256=deadbeef', $secret))->toBeFalse();
});

test('stripe verifies a fresh signature and rejects an expired timestamp', function () {
    $registrar = new StripeRegistrar;
    $secret = 'whsec_test';
    $payload = json_encode(['id' => 'evt_1']);

    $fresh = time();
    $freshSig = "t={$fresh},v1=".hash_hmac('sha256', "{$fresh}.{$payload}", $secret);
    expect($registrar->verifySignature($payload, $freshSig, $secret))->toBeTrue();

    $old = time() - 1000;
    $oldSig = "t={$old},v1=".hash_hmac('sha256', "{$old}.{$payload}", $secret);
    expect($registrar->verifySignature($payload, $oldSig, $secret))->toBeFalse();
});

test('slack verifies its piped timestamp|signature scheme', function () {
    $registrar = new SlackRegistrar;
    $secret = 'slack_signing_secret';
    $payload = json_encode(['event' => ['type' => 'message']]);
    $ts = time();
    $sig = 'v0='.hash_hmac('sha256', "v0:{$ts}:{$payload}", $secret);

    expect($registrar->verifySignature($payload, "{$ts}|{$sig}", $secret))->toBeTrue()
        ->and($registrar->verifySignature($payload, "{$ts}|v0=bad", $secret))->toBeFalse()
        ->and($registrar->verifySignature($payload, 'no-pipe', $secret))->toBeFalse();
});

test('the registrar registry resolves providers and respects auto-registration support', function () {
    expect(WebhookRegistrarRegistry::resolve('github'))->toBeInstanceOf(GitHubRegistrar::class)
        ->and(WebhookRegistrarRegistry::resolve('unknown'))->toBeNull()
        ->and(WebhookRegistrarRegistry::supports('slack'))->toBeTrue()
        // Discord requires manual portal setup, so it is not auto-registerable.
        ->and(WebhookRegistrarRegistry::resolveRegisterable('discord'))->toBeNull()
        ->and(WebhookRegistrarRegistry::resolveRegisterable('github'))->not->toBeNull();
});
