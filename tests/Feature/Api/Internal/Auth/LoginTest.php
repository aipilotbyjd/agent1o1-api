<?php

use App\Models\User;

it('logs in with valid credentials and returns access + refresh tokens', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'user' => ['id', 'email'],
            'tokens' => ['access_token', 'expires_in', 'token_type'],
        ],
    ]);
    $response->assertJsonPath('data.user.email', 'jane@example.com');

    // The refresh token is deliberately kept out of the body: see
    // AuthController::respondWithTokens().
    $response->assertJsonMissingPath('data.tokens.refresh_token');
    expect($response->getCookie('refresh_token', false))->not->toBeNull();
});

it('rejects an invalid password', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable();
});

it('lets a logged in user access a protected route and revokes the token on logout', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $tokens = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens');

    $this->withToken($tokens['access_token'])
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.user.email', $user->email);

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    // TokenGuard caches the resolved user on itself for the request's lifetime, and
    // AuthManager caches that guard instance: real requests each get a fresh container,
    // but simulated test requests share one, so force re-resolution to prove revocation.
    $this->app['auth']->forgetGuards();

    $this->withToken($tokens['access_token'])
        ->getJson('/api/v1/user')
        ->assertUnauthorized();
});
