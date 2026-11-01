<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminToken;
use App\Services\SuperAdmin\Auth\SuperAdminTokenService;
use Database\Factories\SuperAdmin\SuperAdminFactory;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

it('rotates tokens on refresh and kills the session if an old refresh token comes back', function () {
    $admin = superAdmin();
    $first = app(SuperAdminTokenService::class)->issue($admin);

    $second = $this->postJson(saUrl('auth/refresh'), ['refresh_token' => $first['refresh_token']])
        ->assertOk()
        ->json('data.tokens');

    // The rotated pair replaces the old one.
    $this->withToken($first['access_token'])->getJson(saUrl('auth/me'))->assertUnauthorized();
    $this->withToken($second['access_token'])->getJson(saUrl('auth/me'))->assertOk();

    // Presenting the already-rotated refresh token is theft: the whole family dies.
    $this->postJson(saUrl('auth/refresh'), ['refresh_token' => $first['refresh_token']])->assertUnauthorized();
    $this->withToken($second['access_token'])->getJson(saUrl('auth/me'))->assertUnauthorized();
});

it('never extends a session past its absolute lifetime by refreshing', function () {
    $admin = superAdmin();
    $first = app(SuperAdminTokenService::class)->issue($admin);

    $this->postJson(saUrl('auth/refresh'), ['refresh_token' => $first['refresh_token']])->assertOk();

    expect(SuperAdminToken::query()->pluck('absolute_expires_at')->unique())->toHaveCount(1);
});

it('refuses an expired refresh token', function () {
    $admin = superAdmin();
    $issued = app(SuperAdminTokenService::class)->issue($admin);

    $this->travel(8)->days();

    $this->postJson(saUrl('auth/refresh'), ['refresh_token' => $issued['refresh_token']])->assertUnauthorized();
});

it('refuses to refresh a disabled account', function () {
    $admin = superAdmin();
    $issued = app(SuperAdminTokenService::class)->issue($admin);

    $admin->forceFill(['disabled_at' => now()])->save();

    $this->postJson(saUrl('auth/refresh'), ['refresh_token' => $issued['refresh_token']])->assertUnauthorized();
});

it('logs out one session and all sessions', function () {
    $admin = superAdmin();
    $a = app(SuperAdminTokenService::class)->issue($admin);
    $b = app(SuperAdminTokenService::class)->issue($admin);
    $c = app(SuperAdminTokenService::class)->issue($admin);

    $this->withToken($a['access_token'])->postJson(saUrl('auth/logout'))->assertNoContent();
    $this->withToken($a['access_token'])->getJson(saUrl('auth/me'))->assertUnauthorized();
    $this->withToken($b['access_token'])->getJson(saUrl('auth/me'))->assertOk();

    $this->withToken($b['access_token'])->postJson(saUrl('auth/logout-all'))->assertNoContent();
    $this->withToken($b['access_token'])->getJson(saUrl('auth/me'))->assertUnauthorized();
    $this->withToken($c['access_token'])->getJson(saUrl('auth/me'))->assertUnauthorized();
});

it('lists live sessions, marks the current one and revokes another', function () {
    $admin = superAdmin();
    $current = app(SuperAdminTokenService::class)->issue($admin);
    $other = app(SuperAdminTokenService::class)->issue($admin);

    $sessions = $this->withToken($current['access_token'])->getJson(saUrl('auth/sessions'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('data.0.access_token_hash')
        ->json('data');

    expect(collect($sessions)->where('current', true))->toHaveCount(1);

    $this->withToken($current['access_token'])
        ->deleteJson(saUrl('auth/sessions/'.$other['token']->id))
        ->assertNoContent();

    $this->withToken($other['access_token'])->getJson(saUrl('auth/me'))->assertUnauthorized();
});

it('cannot revoke another account\'s session', function () {
    $admin = superAdmin();
    $stranger = superAdmin();
    $strangerSession = app(SuperAdminTokenService::class)->issue($stranger);

    $this->withHeaders(superAdminHeaders($admin))
        ->deleteJson(saUrl('auth/sessions/'.$strangerSession['token']->id))
        ->assertNotFound();
});

it('changes the password, signs other sessions out and keeps the current one', function () {
    $admin = superAdmin();
    $current = app(SuperAdminTokenService::class)->issue($admin);
    $other = app(SuperAdminTokenService::class)->issue($admin);

    $this->withToken($current['access_token'])->postJson(saUrl('auth/change-password'), [
        'current_password' => SuperAdminFactory::PASSWORD,
        'password' => 'Brand-New-Passw0rd!!',
        'password_confirmation' => 'Brand-New-Passw0rd!!',
    ])->assertOk();

    expect(Hash::check('Brand-New-Passw0rd!!', $admin->fresh()->password))->toBeTrue();

    $this->withToken($other['access_token'])->getJson(saUrl('auth/me'))->assertUnauthorized();
    $this->withToken($current['access_token'])->getJson(saUrl('auth/me'))->assertOk();
});

it('rejects a weak, reused or wrongly confirmed password', function (array $payload, string $field) {
    $admin = superAdmin();

    $this->withHeaders(superAdminHeaders($admin))
        ->postJson(saUrl('auth/change-password'), $payload + ['current_password' => SuperAdminFactory::PASSWORD])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'too short' => [['password' => 'Sh0rt!', 'password_confirmation' => 'Sh0rt!'], 'password'],
    'no symbol' => [['password' => 'NoSymbolsInThisOne12345', 'password_confirmation' => 'NoSymbolsInThisOne12345'], 'password'],
    'unconfirmed' => [['password' => 'Brand-New-Passw0rd!!', 'password_confirmation' => 'different'], 'password'],
    'same as current' => [['password' => SuperAdminFactory::PASSWORD, 'password_confirmation' => SuperAdminFactory::PASSWORD], 'password'],
]);

it('rejects a wrong current password', function () {
    $admin = superAdmin();

    $this->withHeaders(superAdminHeaders($admin))->postJson(saUrl('auth/change-password'), [
        'current_password' => 'wrong',
        'password' => 'Brand-New-Passw0rd!!',
        'password_confirmation' => 'Brand-New-Passw0rd!!',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
});

it('blocks everything but a password change while the password is expired', function () {
    $admin = superAdmin(SuperAdminRole::Owner, ['password_changed_at' => null]);

    $headers = superAdminHeaders($admin);

    $this->withHeaders($headers)->getJson(saUrl('auth/me'))->assertOk()->assertJsonPath('data.admin.password_expired', true);

    $this->withHeaders($headers)->getJson(saUrl('audit-log'))
        ->assertForbidden()
        ->assertJsonPath('errors.code', 'password_expired');

    $this->withHeaders($headers)->postJson(saUrl('auth/change-password'), [
        'current_password' => SuperAdminFactory::PASSWORD,
        'password' => 'Brand-New-Passw0rd!!',
        'password_confirmation' => 'Brand-New-Passw0rd!!',
    ])->assertOk();

    $this->withHeaders($headers)->getJson(saUrl('audit-log'))->assertOk();
});

it('enrols two-factor authentication through a setup token, then requires signing in again', function () {
    $admin = SuperAdmin::factory()->owner()->create();

    $setupToken = $this->postJson(saUrl('auth/login'), ['email' => $admin->email, 'password' => SuperAdminFactory::PASSWORD])
        ->json('data.tokens.access_token');

    $secret = $this->withToken($setupToken)->postJson(saUrl('auth/2fa/enable'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['secret', 'otpauth_url']])
        ->json('data.secret');

    $this->withToken($setupToken)->postJson(saUrl('auth/2fa/confirm'), ['code' => 'wrong'])->assertUnprocessable();

    $this->withToken($setupToken)
        ->postJson(saUrl('auth/2fa/confirm'), ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
        ->assertOk()
        ->assertJsonCount(8, 'data.recovery_codes');

    expect($admin->fresh()->hasTwoFactorEnabled())->toBeTrue();

    // The setup token died with the confirmation: only a code-backed login gives a full token now.
    $this->withToken($setupToken)->getJson(saUrl('auth/me'))->assertUnauthorized();

    $this->postJson(saUrl('auth/login'), ['email' => $admin->email, 'password' => SuperAdminFactory::PASSWORD])
        ->assertOk()
        ->assertJsonStructure(['data' => ['two_factor_challenge']]);
});

it('will not restart two-factor enrolment on an account that already has it', function () {
    $admin = superAdmin();

    $this->withHeaders(superAdminHeaders($admin))->postJson(saUrl('auth/2fa/enable'))->assertStatus(409);

    expect($admin->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('regenerates recovery codes only with a reason and a fresh code', function () {
    $admin = superAdmin();
    $headers = superAdminHeaders($admin);

    $this->withHeaders($headers)->postJson(saUrl('auth/2fa/recovery-codes/regenerate'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason', 'totp_code']);

    $this->withHeaders($headers)->postJson(saUrl('auth/2fa/recovery-codes/regenerate'), stepUp($admin))
        ->assertOk()
        ->assertJsonCount(8, 'data.recovery_codes');
});
