<?php

use App\Models\User;

test('a refresh token can be exchanged for a new token pair', function () {
    createPasswordGrantClient();

    User::factory()->create(['email' => 'user@example.com']);

    $refreshToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ])->json('data.token.refresh_token');

    $response = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $refreshToken,
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'success',
            'statusCode',
            'message',
            'data' => [
                'token' => ['token_type', 'expires_in', 'access_token', 'refresh_token'],
            ],
        ]);

    expect($response->json('data.token.refresh_token'))->not->toBe($refreshToken);
});

test('an invalid refresh token is rejected', function () {
    createPasswordGrantClient();

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => 'invalid-refresh-token',
    ])->assertUnauthorized();
});

test('the refresh token is required', function () {
    $this->postJson('/api/v1/auth/refresh')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('refresh_token');
});
