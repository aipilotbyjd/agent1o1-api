<?php

use App\Engine\Webhook\DiscordRegistrar;

test('discord verifies a valid ed25519 signature and rejects an invalid one', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium extension not available');
    }

    $registrar = new DiscordRegistrar;
    $keypair = sodium_crypto_sign_keypair();
    $publicKey = sodium_crypto_sign_publickey($keypair);
    $secretKey = sodium_crypto_sign_secretkey($keypair);
    $publicKeyHex = sodium_bin2hex($publicKey);

    $payload = json_encode(['type' => 1]);
    $timestamp = (string) time();
    $sigBin = sodium_crypto_sign_detached($timestamp.$payload, $secretKey);
    $sigHex = sodium_bin2hex($sigBin);

    expect($registrar->verifySignature($payload, "{$timestamp}|{$sigHex}", $publicKeyHex))->toBeTrue()
        ->and($registrar->verifySignature($payload, "{$timestamp}|".str_repeat('00', 64), $publicKeyHex))->toBeFalse();
});
