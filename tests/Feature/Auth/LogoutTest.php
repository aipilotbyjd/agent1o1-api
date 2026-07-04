<?php

use App\Models\User;

test('a user can logout and their token is revoked', function () {
    createPasswordGrantClient();

    User::factory()->create(['email' => 'user@example.com']);

    $accessToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ])->json('data.token.access_token');

    $headers = ['Authorization' => "Bearer {$accessToken}"];

    $this->postJson('/api/v1/auth/logout', [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logged out successfully.');

    // The guard caches the resolved user across requests within a test,
    // so it must be flushed to re-evaluate the now-revoked token.
    app('auth')->forgetGuards();

    $this->getJson('/api/v1/user', $headers)->assertUnauthorized();
});

test('logout rejects unauthenticated requests', function () {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});
