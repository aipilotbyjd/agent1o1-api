<?php

use App\Enums\SuperAdmin\SuperAdminAuthEvent;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminLoginEvent;
use Database\Factories\SuperAdmin\SuperAdminFactory;
use Illuminate\Support\Facades\Hash;

function superAdminLogin(string $email, string $password = SuperAdminFactory::PASSWORD)
{
    return test()->postJson(saUrl('auth/login'), ['email' => $email, 'password' => $password]);
}

it('gives an account without two-factor authentication a setup token only', function () {
    $admin = SuperAdmin::factory()->owner()->create();

    $response = superAdminLogin($admin->email)
        ->assertOk()
        ->assertJsonPath('data.setup_required', true)
        ->assertJsonPath('data.tokens.refresh_token', null);

    $token = $response->json('data.tokens.access_token');

    $this->withToken($token)->getJson(saUrl('auth/me'))
        ->assertOk()
        ->assertJsonPath('data.token_scope', 'setup');

    $this->withToken($token)->getJson(saUrl('audit-log'))->assertForbidden();
});

it('challenges an account that has two-factor authentication, then trades a code for tokens', function () {
    $admin = superAdmin();

    $login = superAdminLogin($admin->email)
        ->assertOk()
        ->assertJsonStructure(['data' => ['two_factor_challenge']])
        ->assertJsonMissingPath('data.tokens');

    $challenge = $login->json('data.two_factor_challenge');

    $this->postJson(saUrl('auth/2fa/verify'), ['challenge_token' => $challenge, 'code' => superAdminTotp($admin)])
        ->assertOk()
        ->assertJsonPath('data.admin.email', $admin->email)
        ->assertJsonStructure(['data' => ['tokens' => ['access_token', 'refresh_token', 'expires_in', 'token_type']]]);

    expect($admin->fresh()->last_login_at)->not->toBeNull();

    // A challenge is single-use.
    $this->postJson(saUrl('auth/2fa/verify'), ['challenge_token' => $challenge, 'code' => superAdminTotp($admin)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('challenge_token');
});

it('accepts a recovery code once', function () {
    $admin = superAdmin();
    $admin->forceFill(['two_factor_recovery_codes' => [Hash::make('RECOVERY-CODE')]])->save();

    $challenge = superAdminLogin($admin->email)->json('data.two_factor_challenge');

    $this->postJson(saUrl('auth/2fa/verify'), ['challenge_token' => $challenge, 'code' => 'RECOVERY-CODE'])->assertOk();

    $secondChallenge = superAdminLogin($admin->email)->json('data.two_factor_challenge');

    $this->postJson(saUrl('auth/2fa/verify'), ['challenge_token' => $secondChallenge, 'code' => 'RECOVERY-CODE'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

it('burns a challenge after too many wrong codes', function () {
    config(['super_admin_security.rate_limits.auth' => 100]);

    $admin = superAdmin();
    $challenge = superAdminLogin($admin->email)->json('data.two_factor_challenge');

    foreach (range(1, 4) as $attempt) {
        $this->postJson(saUrl('auth/2fa/verify'), ['challenge_token' => $challenge, 'code' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    $this->postJson(saUrl('auth/2fa/verify'), ['challenge_token' => $challenge, 'code' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('challenge_token');

    $this->postJson(saUrl('auth/2fa/verify'), ['challenge_token' => $challenge, 'code' => superAdminTotp($admin)])
        ->assertUnprocessable();
});

it('answers a wrong password, an unknown email and a disabled account identically', function () {
    $admin = SuperAdmin::factory()->create();
    $disabled = SuperAdmin::factory()->disabled()->create();

    $responses = [
        superAdminLogin($admin->email, 'not-the-password'),
        superAdminLogin('nobody@example.test'),
        superAdminLogin($disabled->email),
    ];

    foreach ($responses as $response) {
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
    }
});

it('locks an account after repeated failures and unlocks it later', function () {
    config(['super_admin_security.rate_limits.auth' => 100]);

    $admin = SuperAdmin::factory()->create();

    foreach (range(1, 5) as $attempt) {
        superAdminLogin($admin->email, 'wrong-password')->assertUnprocessable();
    }

    expect($admin->fresh()->isLocked())->toBeTrue();

    // Even the right password is refused while the lock holds.
    superAdminLogin($admin->email)->assertStatus(429);

    $this->travel(16)->minutes();

    superAdminLogin($admin->email)->assertOk();

    expect($admin->fresh()->failed_login_count)->toBe(0);
    expect($admin->fresh()->locked_until)->toBeNull();
});

it('keeps a sign-in history, including attempts at unknown accounts', function () {
    $admin = SuperAdmin::factory()->create();

    superAdminLogin($admin->email, 'wrong-password');
    superAdminLogin('ghost@example.test');
    superAdminLogin($admin->email);

    expect(SuperAdminLoginEvent::query()->where('event', SuperAdminAuthEvent::LoginFailed->value)->count())->toBe(2)
        ->and(SuperAdminLoginEvent::query()->where('email_attempted', 'ghost@example.test')->exists())->toBeTrue()
        ->and(SuperAdminLoginEvent::query()->where('event', SuperAdminAuthEvent::LoginSucceeded->value)->exists())->toBeTrue();
});

it('never returns secrets in the sign-in payload', function () {
    $admin = SuperAdmin::factory()->create();

    superAdminLogin($admin->email)
        ->assertOk()
        ->assertJsonMissingPath('data.admin.password')
        ->assertJsonMissingPath('data.admin.two_factor_secret')
        ->assertJsonMissingPath('data.admin.two_factor_recovery_codes');
});

it('treats the email case-insensitively', function () {
    $admin = SuperAdmin::factory()->create(['email' => 'ops@example.test']);

    superAdminLogin('OPS@Example.test')->assertOk();
});
