<?php

use App\Enums\Auth\AuthEvent;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PragmaRX\Google2FA\Google2FA;

function currentOtpFor(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

it('enables and confirms two-factor, returning recovery codes', function () {
    $user = User::factory()->create(['password' => 'Password1!']);

    $enable = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'Password1!'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['secret', 'otpauth_url']])
        ->json('data');

    $code = currentOtpFor($enable['secret']);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => $code])
        ->assertOk()
        ->assertJsonStructure(['data' => ['recovery_codes']])
        ->assertJsonCount(8, 'data.recovery_codes');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('rejects an invalid confirmation code', function () {
    $user = User::factory()->create(['password' => 'Password1!']);

    $this->actingAs($user, 'api')->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'Password1!']);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => '000000'])
        ->assertStatus(422);
});

it('returns a two-factor challenge on login instead of tokens, then completes it', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $enable = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'Password1!'])
        ->json('data');

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => currentOtpFor($enable['secret'])]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->assertOk()
        ->assertJsonStructure(['data' => ['two_factor_challenge']])
        ->json('data');

    $verify = $this->postJson('/api/v1/auth/2fa/verify', [
        'challenge_token' => $login['two_factor_challenge'],
        'code' => currentOtpFor($enable['secret']),
    ])->assertOk()
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'email'],
                'tokens' => ['access_token', 'expires_in', 'token_type'],
            ],
        ]);

    $this->withToken($verify->json('data.tokens.access_token'))
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.user.email', $user->email);
});

it('rejects an invalid two-factor verification code', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $enable = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'Password1!'])
        ->json('data');

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => currentOtpFor($enable['secret'])]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data');

    $this->postJson('/api/v1/auth/2fa/verify', [
        'challenge_token' => $login['two_factor_challenge'],
        'code' => '000000',
    ])->assertStatus(422);
});

it('disables two-factor and login goes straight to tokens again', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $enable = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'Password1!'])
        ->json('data');

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => currentOtpFor($enable['secret'])]);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/disable', ['current_password' => 'Password1!'])
        ->assertNoContent();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->assertOk()->assertJsonStructure(['data' => ['user', 'tokens']]);
});

it('refuses to enable two-factor without the current password', function () {
    $user = User::factory()->create(['password' => 'Password1!']);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable')
        ->assertStatus(422)
        ->assertJsonPath('errors.current_password.0', 'The current password field is required.');

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'not-the-password'])
        ->assertStatus(422);

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

it('refuses to regenerate recovery codes without the current password', function () {
    $user = User::factory()->create(['password' => 'Password1!']);

    $enable = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'Password1!'])
        ->json('data');

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => currentOtpFor($enable['secret'])]);

    $original = $user->fresh()->two_factor_recovery_codes;

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/recovery-codes/regenerate')
        ->assertStatus(422);

    expect($user->fresh()->two_factor_recovery_codes)->toBe($original);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/recovery-codes/regenerate', ['current_password' => 'Password1!'])
        ->assertOk()
        ->assertJsonCount(8, 'data.recovery_codes');

    expect($user->fresh()->two_factor_recovery_codes)->not->toBe($original);
});

it('burns a two-factor challenge after five wrong codes', function () {
    // The challenge's own attempt cap is what is under test; the per-minute
    // limiter would otherwise answer the last of these requests first.
    $this->withoutMiddleware(ThrottleRequests::class);

    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    $enable = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'Password1!'])
        ->json('data');

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => currentOtpFor($enable['secret'])]);

    $challengeToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.two_factor_challenge');

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $challengeToken,
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('errors.code.0', 'The provided two-factor code is invalid.');
    }

    // The fifth wrong code burns the challenge...
    $this->postJson('/api/v1/auth/2fa/verify', [
        'challenge_token' => $challengeToken,
        'code' => '000000',
    ])->assertStatus(422)->assertJsonPath('errors.challenge_token.0', 'Too many incorrect codes. Sign in again to start a new challenge.');

    // ...so even the right code cannot rescue it.
    $this->postJson('/api/v1/auth/2fa/verify', [
        'challenge_token' => $challengeToken,
        'code' => currentOtpFor($enable['secret']),
    ])->assertStatus(422)->assertJsonPath('errors.challenge_token.0', 'This two-factor challenge has expired.');
});

it('records two-factor changes in the audit trail', function () {
    $user = User::factory()->create(['password' => 'Password1!']);

    $enable = $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'Password1!'])
        ->json('data');

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => currentOtpFor($enable['secret'])]);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/2fa/disable', ['current_password' => 'Password1!']);

    expect($user->authEvents()->pluck('event')->all())
        ->toContain(AuthEvent::TwoFactorEnabled)
        ->toContain(AuthEvent::TwoFactorDisabled);
});
