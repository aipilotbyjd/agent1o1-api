<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Notifications\SuperAdmin\SuperAdminAlertNotification;
use App\Services\SuperAdmin\Accounts\SuperAdminAccountService;
use App\Services\SuperAdmin\Auth\SuperAdminTokenService;
use Database\Factories\SuperAdmin\SuperAdminFactory;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('lets an owner create an account, records why, and the account can sign in with the temporary password', function () {
    $owner = superAdmin();

    $response = $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl('accounts'), stepUp($owner, ['name' => 'Ops Person', 'email' => 'Ops@Example.test', 'role' => 'support']))
        ->assertCreated()
        ->assertJsonPath('data.admin.email', 'ops@example.test')
        ->assertJsonPath('data.admin.role', 'support')
        ->assertJsonPath('data.admin.password_expired', true)
        ->assertJsonPath('data.admin.two_factor_enabled', false)
        ->assertJsonStructure(['data' => ['temporary_password']]);

    $log = SuperAdminAuditLog::query()->where('action', 'accounts.created')->firstOrFail();

    expect($log->reason)->toBe('Verified request from the account owner')
        ->and($log->super_admin_id)->toBe($owner->id)
        ->and($log->after)->toBe(['email' => 'ops@example.test', 'role' => 'support'])
        ->and(json_encode($log->toArray()))->not->toContain($response->json('data.temporary_password'));

    $this->flushHeaders()
        ->postJson(saUrl('auth/login'), ['email' => 'ops@example.test', 'password' => $response->json('data.temporary_password')])
        ->assertOk()
        ->assertJsonPath('data.setup_required', true);
});

it('requires a reason and a valid fresh authenticator code for sensitive writes', function () {
    $owner = superAdmin();
    $headers = superAdminHeaders($owner);
    $payload = ['name' => 'Ops Person', 'email' => 'ops@example.test', 'role' => 'support'];

    $this->withHeaders($headers)->postJson(saUrl('accounts'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason', 'totp_code']);

    $this->withHeaders($headers)->postJson(saUrl('accounts'), [...$payload, 'reason' => 'short', 'totp_code' => superAdminTotp($owner)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    $this->withHeaders($headers)->postJson(saUrl('accounts'), [...stepUp($owner, $payload), 'totp_code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('totp_code');

    expect(SuperAdmin::query()->where('email', 'ops@example.test')->exists())->toBeFalse();
});

it('rejects a duplicate email, an unknown role and a bad IP entry', function () {
    $owner = superAdmin();
    $headers = superAdminHeaders($owner);

    $this->withHeaders($headers)
        ->postJson(saUrl('accounts'), stepUp($owner, ['name' => 'Dup', 'email' => $owner->email, 'role' => 'viewer']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->withHeaders($headers)
        ->postJson(saUrl('accounts'), stepUp($owner, ['name' => 'X', 'email' => 'x@example.test', 'role' => 'emperor']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->withHeaders($headers)
        ->postJson(saUrl('accounts'), stepUp($owner, ['name' => 'X', 'email' => 'x@example.test', 'role' => 'viewer', 'allowed_ips' => ['not-an-ip', '10.0.0.0/99']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['allowed_ips.0', 'allowed_ips.1']);
});

it('keeps account management away from every other role', function (SuperAdminRole $role) {
    $admin = superAdmin($role);
    $headers = superAdminHeaders($admin);
    $target = superAdmin(SuperAdminRole::Viewer);

    $this->withHeaders($headers)->postJson(saUrl('accounts'), stepUp($admin, ['name' => 'X', 'email' => 'x@example.test', 'role' => 'owner']))->assertForbidden();
    $this->withHeaders($headers)->postJson(saUrl("accounts/{$target->id}/role"), stepUp($admin, ['role' => 'owner']))->assertForbidden();
    $this->withHeaders($headers)->postJson(saUrl("accounts/{$target->id}/disable"), stepUp($admin))->assertForbidden();
    $this->withHeaders($headers)->postJson(saUrl("accounts/{$target->id}/reset-2fa"), stepUp($admin))->assertForbidden();
})->with([
    'operator' => [SuperAdminRole::Operator],
    'finance' => [SuperAdminRole::Finance],
    'support' => [SuperAdminRole::Support],
    'viewer' => [SuperAdminRole::Viewer],
]);

it('lists and shows accounts without secrets', function () {
    $owner = superAdmin();
    superAdmin(SuperAdminRole::Support);

    $headers = superAdminHeaders($owner);

    $list = $this->withHeaders($headers)->getJson(saUrl('accounts'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('data.0.password')
        ->assertJsonMissingPath('data.0.two_factor_secret')
        ->assertJsonMissingPath('data.0.two_factor_recovery_codes');

    expect($list->json('meta.total'))->toBe(2);

    $this->withHeaders($headers)->getJson(saUrl("accounts/{$owner->id}"))
        ->assertOk()
        ->assertJsonPath('data.email', $owner->email)
        ->assertJsonPath('data.two_factor_enabled', true);

    $this->withHeaders($headers)->getJson(saUrl('accounts?role=support'))->assertJsonCount(1, 'data');
    $this->withHeaders($headers)->getJson(saUrl('accounts?q='.urlencode($owner->email)))->assertJsonCount(1, 'data');
});

it('changes a role, with the before and after in the audit log', function () {
    $owner = superAdmin();
    $target = superAdmin(SuperAdminRole::Support);

    $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl("accounts/{$target->id}/role"), stepUp($owner, ['role' => 'finance']))
        ->assertOk()
        ->assertJsonPath('data.role', 'finance');

    $log = SuperAdminAuditLog::query()->where('action', 'accounts.role_changed')->firstOrFail();

    expect($target->fresh()->role)->toBe(SuperAdminRole::Finance)
        ->and($log->before)->toBe(['role' => 'support'])
        ->and($log->after)->toBe(['role' => 'finance'])
        ->and($log->subject_id)->toBe($target->id);
});

it('updates the name and the IP allowlist', function () {
    $owner = superAdmin();
    $target = superAdmin(SuperAdminRole::Support);

    $this->withHeaders(superAdminHeaders($owner))
        ->patchJson(saUrl("accounts/{$target->id}"), ['name' => 'Renamed', 'allowed_ips' => ['203.0.113.0/24', '2001:db8::1']])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.allowed_ips', ['203.0.113.0/24', '2001:db8::1']);

    expect(SuperAdminAuditLog::query()->where('action', 'accounts.updated')->exists())->toBeTrue();
});

it('will not let an owner act on their own account', function (string $path, array $extra) {
    $owner = superAdmin();
    superAdmin(); // a second owner, so only the self rule can be what refuses

    $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl("accounts/{$owner->id}/{$path}"), stepUp($owner, $extra))
        ->assertStatus(409);

    $owner = $owner->fresh();

    expect($owner->role)->toBe(SuperAdminRole::Owner);
    expect($owner->disabled_at)->toBeNull();
    expect($owner->hasTwoFactorEnabled())->toBeTrue();
})->with([
    'change own role' => ['role', ['role' => 'viewer']],
    'disable self' => ['disable', []],
    'reset own two-factor' => ['reset-2fa', []],
]);

it('disables an account, ending its sessions, and enables it again', function () {
    $owner = superAdmin();
    $target = superAdmin(SuperAdminRole::Operator);
    $targetHeaders = superAdminHeaders($target);

    $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl("accounts/{$target->id}/disable"), stepUp($owner))
        ->assertOk();

    $this->withHeaders($targetHeaders)->getJson(saUrl('auth/me'))->assertUnauthorized();

    $this->flushHeaders()
        ->postJson(saUrl('auth/login'), ['email' => $target->email, 'password' => SuperAdminFactory::PASSWORD])
        ->assertUnprocessable();

    $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl("accounts/{$target->id}/enable"), stepUp($owner))
        ->assertOk();

    $this->flushHeaders()
        ->postJson(saUrl('auth/login'), ['email' => $target->email, 'password' => SuperAdminFactory::PASSWORD])
        ->assertOk();
});

it('resets two-factor authentication and signs the account out everywhere', function () {
    $owner = superAdmin();
    $target = superAdmin(SuperAdminRole::Operator);
    $targetHeaders = superAdminHeaders($target);

    $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl("accounts/{$target->id}/reset-2fa"), stepUp($owner))
        ->assertOk()
        ->assertJsonPath('data.two_factor_enabled', false);

    expect($target->fresh()->two_factor_secret)->toBeNull();

    $this->withHeaders($targetHeaders)->getJson(saUrl('auth/me'))->assertUnauthorized();
    expect(SuperAdminAuditLog::query()->where('action', 'accounts.two_factor_reset')->exists())->toBeTrue();
});

it('revokes all sessions of an account on request', function () {
    $owner = superAdmin();
    $target = superAdmin(SuperAdminRole::Support);
    $targetHeaders = superAdminHeaders($target);

    $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl("accounts/{$target->id}/revoke-sessions"), stepUp($owner))
        ->assertOk();

    $this->withHeaders($targetHeaders)->getJson(saUrl('auth/me'))->assertUnauthorized();
});

it('ends every session when an owner is demoted', function () {
    $owner = superAdmin();
    $other = superAdmin();
    $otherHeaders = superAdminHeaders($other);

    $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl("accounts/{$other->id}/role"), stepUp($owner, ['role' => 'viewer']))
        ->assertOk();

    $this->withHeaders($otherHeaders)->getJson(saUrl('auth/me'))->assertUnauthorized();
});

it('takes away the ability to refresh when any role loses abilities', function () {
    $owner = superAdmin();
    $target = superAdmin(SuperAdminRole::Operator);
    $issued = app(SuperAdminTokenService::class)->issue($target);

    $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl("accounts/{$target->id}/role"), stepUp($owner, ['role' => 'viewer']))
        ->assertOk();

    $this->travel(1)->seconds();

    $this->postJson(saUrl('auth/refresh'), ['refresh_token' => $issued['refresh_token']])->assertUnauthorized();
});

it('leaves refresh alone when a role gains abilities', function () {
    $owner = superAdmin();
    $target = superAdmin(SuperAdminRole::Viewer);
    $issued = app(SuperAdminTokenService::class)->issue($target);

    $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl("accounts/{$target->id}/role"), stepUp($owner, ['role' => 'operator']))
        ->assertOk();

    $this->postJson(saUrl('auth/refresh'), ['refresh_token' => $issued['refresh_token']])->assertOk();
});

it('always keeps one active owner', function () {
    $onlyOwner = superAdmin();
    $service = app(SuperAdminAccountService::class);

    expect(fn () => $service->changeRole(null, $onlyOwner, SuperAdminRole::Viewer))
        ->toThrow(HttpException::class, 'At least one active owner must remain.');

    expect(fn () => $service->disable(null, $onlyOwner))
        ->toThrow(HttpException::class, 'At least one active owner must remain.');

    // A disabled owner does not count as a remaining owner.
    $second = superAdmin();
    $service->disable(null, $second);

    expect(fn () => $service->disable(null, $onlyOwner))
        ->toThrow(HttpException::class);

    expect($onlyOwner->fresh()->isDisabled())->toBeFalse();
});

it('raises a security alert when a role changes', function () {
    Notification::fake();
    config(['super_admin_alerts.recipients.mail' => ['security@example.test']]);

    $owner = superAdmin();
    $target = superAdmin(SuperAdminRole::Support);

    $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl("accounts/{$target->id}/role"), stepUp($owner, ['role' => 'finance']))
        ->assertOk();

    Notification::assertSentOnDemand(SuperAdminAlertNotification::class, fn (SuperAdminAlertNotification $alert): bool => $alert->event === 'role_changed');
});

it('shows an account\'s sign-in history to owners', function () {
    $owner = superAdmin();
    $target = superAdmin(SuperAdminRole::Support);

    $this->flushHeaders()->postJson(saUrl('auth/login'), ['email' => $target->email, 'password' => 'wrong-password']);

    $this->withHeaders(superAdminHeaders($owner))
        ->getJson(saUrl("accounts/{$target->id}/login-events"))
        ->assertOk()
        ->assertJsonPath('data.0.event', 'login_failed');
});
