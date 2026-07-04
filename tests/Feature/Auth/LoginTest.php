<?php

use App\Models\User;

test('a user can login with valid credentials', function () {
    createPasswordGrantClient();

    $user = User::factory()->create(['email' => 'user@example.com']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure([
            'success',
            'statusCode',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token' => ['token_type', 'expires_in', 'access_token', 'refresh_token'],
            ],
        ]);
});

test('login fails with invalid credentials', function () {
    User::factory()->create(['email' => 'user@example.com']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('login fails for an unknown email', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'missing@example.com',
        'password' => 'password',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('an authenticated user can fetch their profile', function () {
    createPasswordGrantClient();

    $user = User::factory()->create(['email' => 'user@example.com']);

    $accessToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ])->json('data.token.access_token');

    $this->getJson('/api/v1/user', ['Authorization' => "Bearer {$accessToken}"])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id);
});

test('the user endpoint rejects unauthenticated requests', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});
