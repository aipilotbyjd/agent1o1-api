<?php

use App\Enums\Auth\AuthEvent;
use App\Models\User;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

it('revokes the access token and its refresh token on logout', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $tokens = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $tokenId = $user->tokens()->latest()->first()->id;

    expect(Token::query()->whereKey($tokenId)->value('revoked'))->toBeTrue();
    expect(RefreshToken::query()->where('access_token_id', $tokenId)->value('revoked'))->toBeTrue();
});

it('leaves a refresh token unusable after logout', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $refreshCookie = $login->getCookie('refresh_token', false);

    $this->withToken($login->json('data.tokens.access_token'))
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->withCredentials()->withUnencryptedCookie('refresh_token', $refreshCookie->getValue())
        ->postJson('/api/v1/auth/refresh')
        ->assertStatus(422);
});

it('revokes every session on logout-all', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $first = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $second = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $this->withToken($second)
        ->postJson('/api/v1/auth/logout-all')
        ->assertNoContent();

    $this->app['auth']->forgetGuards();

    $this->withToken($first)->getJson('/api/v1/user')->assertUnauthorized();

    $this->app['auth']->forgetGuards();

    $this->withToken($second)->getJson('/api/v1/user')->assertUnauthorized();

    expect($user->tokens()->where('revoked', false)->count())->toBe(0);
    expect(RefreshToken::query()->where('revoked', false)->count())->toBe(0);
});

it('records logout events in the audit trail', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $this->withToken($token)->postJson('/api/v1/auth/logout');

    expect($user->authEvents()->pluck('event')->all())->toContain(AuthEvent::LoggedOut);
});

it('rejects a logout without a token', function () {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});
