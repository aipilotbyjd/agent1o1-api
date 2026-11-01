<?php

use App\Enums\Auth\AuthEvent;
use App\Models\Auth\AuthEventLog;
use App\Models\User;
use Laravel\Passport\RefreshToken;
use PragmaRX\Google2FA\Google2FA;

it('returns the signed-in user with their security state', function () {
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.name', 'Jane Doe')
        ->assertJsonPath('data.user.email', 'jane@example.com')
        ->assertJsonPath('data.user.two_factor_enabled', false)
        ->assertJsonPath('data.user.pending_email', null)
        ->assertJsonMissingPath('data.user.password');
});

it('reports two-factor as enabled once it is confirmed', function () {
    $user = User::factory()->create(['password' => 'Password1!']);

    $secret = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'Password1!'])
        ->json('data.secret');

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => app(Google2FA::class)->getCurrentOtp($secret)]);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/user')
        ->assertJsonPath('data.user.two_factor_enabled', true);
});

it('refuses the current user endpoint to a guest', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});

it('deletes the account and revokes both token kinds', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $userId = $user->id;

    $this->withToken($token)->deleteJson('/api/v1/user')->assertNoContent();

    expect(User::query()->whereKey($userId)->exists())->toBeFalse();
    expect(RefreshToken::query()->where('revoked', false)->count())->toBe(0);

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/user')->assertUnauthorized();
});

it('logs the deletion before the account goes', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')->deleteJson('/api/v1/user')->assertNoContent();

    // The log outlives the account: user_id is nulled, the address is kept.
    $event = AuthEventLog::query()
        ->where('email', 'jane@example.com')
        ->where('event', AuthEvent::AccountDeleted->value)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->user_id)->toBeNull();
});
