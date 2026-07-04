<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('a password reset link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'user@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'user@example.com'])
        ->assertSuccessful();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('requesting a reset link for an unknown email still succeeds', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.com'])
        ->assertSuccessful();

    Notification::assertNothingSent();
});

test('a password can be reset with a valid token', function () {
    createPasswordGrantClient();

    $user = User::factory()->create(['email' => 'user@example.com']);

    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'user@example.com',
        'password' => 'NewSecret#123',
        'password_confirmation' => 'NewSecret#123',
    ])->assertSuccessful();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'NewSecret#123',
    ])->assertSuccessful();
});

test('resetting the password revokes all existing tokens', function () {
    createPasswordGrantClient();

    $user = User::factory()->create(['email' => 'user@example.com']);

    $accessToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ])->json('token.access_token');

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => Password::createToken($user),
        'email' => 'user@example.com',
        'password' => 'NewSecret#123',
        'password_confirmation' => 'NewSecret#123',
    ])->assertSuccessful();

    $this->getJson('/api/v1/user', ['Authorization' => "Bearer {$accessToken}"])
        ->assertUnauthorized();
});

test('a password cannot be reset with an invalid token', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => 'user@example.com',
        'password' => 'NewSecret#123',
        'password_confirmation' => 'NewSecret#123',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});
