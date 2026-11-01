<?php

use App\Enums\Auth\AuthEvent;
use App\Models\User;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

it('lists active sessions with device metadata and marks the current one', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.4', 'HTTP_USER_AGENT' => 'OldPhone/1.0'])
        ->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'Password1!']);

    $current = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'Desktop/9.9'])
        ->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'Password1!'])
        ->json('data.tokens.access_token');

    $sessions = $this->withToken($current)
        ->getJson('/api/v1/auth/sessions')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['sessions' => [['id', 'client_name', 'ip_address', 'user_agent', 'last_used_at', 'is_current', 'created_at']]],
        ])
        ->json('data.sessions');

    expect($sessions)->toHaveCount(2);

    $currentSession = collect($sessions)->firstWhere('is_current', true);
    $otherSession = collect($sessions)->firstWhere('is_current', false);

    expect($currentSession)->not->toBeNull()
        ->and($currentSession['user_agent'])->toBe('Desktop/9.9')
        ->and($currentSession['ip_address'])->toBe('203.0.113.7')
        ->and($currentSession['client_name'])->toBe('Testing Password Grant Client')
        ->and($otherSession['user_agent'])->toBe('OldPhone/1.0');
});

it('revokes a named session and its refresh token', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $doomed = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $keeper = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $doomedId = collect($this->withToken($keeper)->getJson('/api/v1/auth/sessions')->json('data.sessions'))
        ->firstWhere('is_current', false)['id'];

    $this->withToken($keeper)
        ->deleteJson("/api/v1/auth/sessions/{$doomedId}")
        ->assertNoContent();

    expect(RefreshToken::query()->where('access_token_id', $doomedId)->value('revoked'))->toBeTrue();

    $this->app['auth']->forgetGuards();
    $this->withToken($doomed)->getJson('/api/v1/user')->assertUnauthorized();

    $this->app['auth']->forgetGuards();
    $this->withToken($keeper)->getJson('/api/v1/user')->assertOk();

    expect($user->authEvents()->pluck('event')->all())->toContain(AuthEvent::SessionRevoked);
});

it('will not let one user revoke another user\'s session', function () {
    $victim = User::factory()->create(['email' => 'victim@example.com', 'password' => 'Password1!']);
    $attacker = User::factory()->create(['email' => 'attacker@example.com', 'password' => 'Password1!']);

    $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'Password1!']);
    $attackerToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'attacker@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $victimSessionId = $victim->tokens()->first()->id;

    $this->withToken($attackerToken)
        ->deleteJson("/api/v1/auth/sessions/{$victimSessionId}")
        ->assertNotFound();

    expect($victim->tokens()->where('revoked', false)->count())->toBe(1);
});

it('hides revoked sessions from the list', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'Password1!']);

    $current = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $staleId = collect($this->withToken($current)->getJson('/api/v1/auth/sessions')->json('data.sessions'))
        ->firstWhere('is_current', false)['id'];

    Token::query()->whereKey($staleId)->update(['revoked' => true]);

    $sessions = $this->withToken($current)->getJson('/api/v1/auth/sessions')->json('data.sessions');

    expect($sessions)->toHaveCount(1)
        ->and($sessions[0]['is_current'])->toBeTrue();
});
