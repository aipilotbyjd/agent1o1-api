<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminToken;
use App\Models\User;
use App\Services\SuperAdmin\Auth\SuperAdminTokenService;
use Database\Factories\SuperAdmin\SuperAdminFactory;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;

it('refuses a user token on every Super Admin route', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson(saUrl('auth/me'))->assertUnauthorized();
    $this->getJson(saUrl('audit-log'))->assertUnauthorized();
    $this->getJson(saUrl('accounts'))->assertUnauthorized();
});

it('refuses a Super Admin token on the user API', function () {
    $admin = superAdmin();

    $this->withHeaders(superAdminHeaders($admin))->getJson('/api/v1/user')->assertUnauthorized();
});

it('refuses requests without a token', function () {
    $this->getJson(saUrl('auth/me'))->assertUnauthorized();
    $this->getJson(saUrl('audit-log'))->assertUnauthorized();
});

it('refuses a token once the access lifetime has passed', function () {
    $admin = superAdmin();
    $headers = superAdminHeaders($admin);

    $this->withHeaders($headers)->getJson(saUrl('auth/me'))->assertOk();

    $this->travel(16)->minutes();

    $this->withHeaders($headers)->getJson(saUrl('auth/me'))->assertUnauthorized();
});

it('refuses the tokens of a disabled account immediately', function () {
    $admin = superAdmin();
    $headers = superAdminHeaders($admin);

    $admin->forceFill(['disabled_at' => now()])->save();

    $this->withHeaders($headers)->getJson(saUrl('auth/me'))->assertUnauthorized();
});

it('rejects every token while the master switch is off', function () {
    $admin = superAdmin();
    $headers = superAdminHeaders($admin);

    config(['super_admin.enabled' => false]);

    $this->withHeaders($headers)->getJson(saUrl('auth/me'))->assertUnauthorized();
});

it('keeps a setup token away from everything beyond the setup endpoints', function () {
    $admin = superAdmin();
    $headers = superAdminHeaders($admin, SuperAdminToken::SCOPE_SETUP);

    $this->withHeaders($headers)->getJson(saUrl('auth/me'))->assertOk();
    $this->withHeaders($headers)->getJson(saUrl('audit-log'))->assertForbidden();
    $this->withHeaders($headers)->getJson(saUrl('accounts'))->assertForbidden();
    $this->withHeaders($headers)->getJson(saUrl('auth/sessions'))->assertForbidden();
});

it('refuses a full token once the account has lost its two-factor enrolment', function () {
    $admin = superAdmin();
    $headers = superAdminHeaders($admin);

    $admin->forceFill(['two_factor_confirmed_at' => null])->save();

    $this->withHeaders($headers)->getJson(saUrl('audit-log'))->assertForbidden();
});

it('blocks everyone outside the global IP allowlist, sign-in included', function () {
    config(['super_admin_security.allowed_ips' => ['203.0.113.0/24']]);

    $admin = SuperAdmin::factory()->create();

    $this->postJson(saUrl('auth/login'), ['email' => $admin->email, 'password' => SuperAdminFactory::PASSWORD])
        ->assertForbidden();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->postJson(saUrl('auth/login'), ['email' => $admin->email, 'password' => SuperAdminFactory::PASSWORD])
        ->assertOk();
});

it('applies an account\'s own IP allowlist to sign-in and to its tokens', function () {
    $admin = SuperAdmin::factory()->create(['allowed_ips' => ['203.0.113.5']]);

    $this->postJson(saUrl('auth/login'), ['email' => $admin->email, 'password' => SuperAdminFactory::PASSWORD])
        ->assertForbidden();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->postJson(saUrl('auth/login'), ['email' => $admin->email, 'password' => SuperAdminFactory::PASSWORD])
        ->assertOk();

    $fullAdmin = superAdmin(SuperAdminRole::Owner, ['allowed_ips' => ['203.0.113.5']]);

    // Server variables persist across requests in a test, so set the address explicitly each time.
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->withHeaders(superAdminHeaders($fullAdmin))
        ->getJson(saUrl('audit-log'))
        ->assertForbidden();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->withHeaders(superAdminHeaders($fullAdmin))
        ->getJson(saUrl('audit-log'))
        ->assertOk();
});

it('fails closed on an ability name that does not exist', function () {
    Route::middleware(['auth:super-admin', 'super-admin.can:no.such-ability'])
        ->get('/api/super-admin-boundary-test', fn () => 'reached');

    $this->withHeaders(superAdminHeaders(superAdmin()))
        ->getJson('/api/super-admin-boundary-test')
        ->assertForbidden();
});

it('never issues a refresh token with a setup session', function () {
    $admin = superAdmin();
    $issued = app(SuperAdminTokenService::class)->issue($admin, SuperAdminToken::SCOPE_SETUP);

    expect($issued['refresh_token'])->toBeNull()
        ->and($issued['token']->refresh_token_hash)->toBeNull();
});
