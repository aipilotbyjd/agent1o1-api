<?php

use App\Models\User;

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

it('rejects registration with a mismatched password confirmation', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'nope',
    ])->assertUnprocessable();
});
