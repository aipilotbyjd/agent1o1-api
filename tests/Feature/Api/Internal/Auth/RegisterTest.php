<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;

it('registers a user without a workspace and issues tokens', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => [
            'user' => ['id', 'name', 'email'],
            'tokens' => ['access_token', 'expires_in', 'token_type'],
        ],
    ]);

    $response->assertJsonMissingPath('data.tokens.refresh_token');
    expect($response->getCookie('refresh_token', false))->not->toBeNull();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->current_workspace_id)->toBeNull();
    expect($user->workspaces()->count())->toBe(0);
});

it('registers without a password confirmation, since the form has one password field', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->assertCreated();
});

it('still rejects a weak password', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'short',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('keeps where the signup came from', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'attribution' => [
            'utm_source' => 'newsletter',
            'utm_campaign' => 'launch',
            'landing_path' => '/templates/lead-intake-and-scoring',
            'template' => 'lead-intake-and-scoring',
            'unknown_field' => 'dropped',
        ],
    ])->assertCreated();

    expect(User::query()->where('email', 'jane@example.com')->firstOrFail()->signup_attribution)->toBe([
        'utm_source' => 'newsletter',
        'utm_campaign' => 'launch',
        'landing_path' => '/templates/lead-intake-and-scoring',
        'template' => 'lead-intake-and-scoring',
    ]);
});

it('saves no account when the tokens cannot be issued, so the same email can retry', function () {
    Notification::fake();
    $secret = config('passport.password_client_secret');
    config(['passport.password_client_secret' => 'not-the-real-secret']);

    $payload = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ];

    $this->postJson('/api/v1/auth/register', $payload)->assertUnprocessable();

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();

    config(['passport.password_client_secret' => $secret]);

    $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
});
