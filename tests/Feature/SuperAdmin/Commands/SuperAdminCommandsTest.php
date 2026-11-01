<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\SuperAdmin\Auth\SuperAdminTokenService;

it('creates a super admin from the command line, with an audit entry naming the command line', function () {
    $this->artisan('super-admin:create', ['email' => 'Boss@Example.test', '--name' => 'The Boss', '--role' => 'owner'])
        ->assertSuccessful();

    $admin = SuperAdmin::query()->where('email', 'boss@example.test')->firstOrFail();

    expect($admin->role)->toBe(SuperAdminRole::Owner)
        ->and($admin->name)->toBe('The Boss')
        ->and($admin->passwordExpired())->toBeTrue()
        ->and($admin->hasTwoFactorEnabled())->toBeFalse();

    $log = SuperAdminAuditLog::query()->where('action', 'accounts.created')->firstOrFail();

    expect($log->super_admin_id)->toBeNull()
        ->and($log->actor_label)->toStartWith('cli:');
});

it('refuses a duplicate, a bad email and an unknown role', function () {
    superAdmin()->forceFill(['email' => 'taken@example.test'])->save();

    $this->artisan('super-admin:create', ['email' => 'taken@example.test'])->assertFailed();
    $this->artisan('super-admin:create', ['email' => 'not-an-email'])->assertFailed();
    $this->artisan('super-admin:create', ['email' => 'new@example.test', '--role' => 'emperor'])->assertFailed();

    expect(SuperAdmin::query()->count())->toBe(1);
});

it('disables and re-enables an account', function () {
    superAdmin();
    $target = superAdmin(SuperAdminRole::Support);
    $headers = superAdminHeaders($target);

    $this->artisan('super-admin:disable', ['email' => $target->email])->assertSuccessful();

    expect($target->fresh()->isDisabled())->toBeTrue();
    $this->withHeaders($headers)->getJson(saUrl('auth/me'))->assertUnauthorized();

    $this->artisan('super-admin:enable', ['email' => $target->email])->assertSuccessful();

    expect($target->fresh()->isDisabled())->toBeFalse();
});

it('resets two-factor authentication and revokes tokens', function () {
    $target = superAdmin();
    $headers = superAdminHeaders($target);

    $this->artisan('super-admin:reset-2fa', ['email' => $target->email])->assertSuccessful();

    expect($target->fresh()->hasTwoFactorEnabled())->toBeFalse();
    $this->withHeaders($headers)->getJson(saUrl('auth/me'))->assertUnauthorized();

    $this->artisan('super-admin:revoke-tokens', ['email' => $target->email])->assertSuccessful();
});

it('sets a role, but never leaves the platform without an active owner', function () {
    $owner = superAdmin();
    $other = superAdmin(SuperAdminRole::Viewer);

    $this->artisan('super-admin:set-role', ['email' => $other->email, 'role' => 'finance'])->assertSuccessful();
    expect($other->fresh()->role)->toBe(SuperAdminRole::Finance);

    $this->artisan('super-admin:set-role', ['email' => $owner->email, 'role' => 'viewer'])->assertFailed();
    $this->artisan('super-admin:disable', ['email' => $owner->email])->assertFailed();

    expect($owner->fresh()->role)->toBe(SuperAdminRole::Owner);
    expect($owner->fresh()->disabled_at)->toBeNull();
});

it('fails for an unknown account or an unknown role', function () {
    $this->artisan('super-admin:disable', ['email' => 'ghost@example.test'])->assertFailed();
    $this->artisan('super-admin:set-role', ['email' => 'ghost@example.test', 'role' => 'emperor'])->assertFailed();
});

it('prints the audit log', function () {
    $this->artisan('super-admin:create', ['email' => 'boss@example.test', '--role' => 'owner'])->assertSuccessful();

    $this->artisan('super-admin:audit-log', ['--action' => 'accounts.', '--limit' => 5])->assertSuccessful();
});

it('refuses to run any command while the master switch is off', function () {
    $target = superAdmin();

    config(['super_admin.enabled' => false]);

    $this->artisan('super-admin:create', ['email' => 'new@example.test'])->assertFailed();
    $this->artisan('super-admin:disable', ['email' => $target->email])->assertFailed();
    $this->artisan('super-admin:enable', ['email' => $target->email])->assertFailed();
    $this->artisan('super-admin:reset-2fa', ['email' => $target->email])->assertFailed();
    $this->artisan('super-admin:set-role', ['email' => $target->email, 'role' => 'viewer'])->assertFailed();
    $this->artisan('super-admin:revoke-tokens', ['email' => $target->email])->assertFailed();
    $this->artisan('super-admin:audit-log')->assertFailed();

    expect(SuperAdmin::query()->where('email', 'new@example.test')->exists())->toBeFalse()
        ->and($target->fresh()->isDisabled())->toBeFalse();
});

it('keeps token revocation working through the service for commands', function () {
    $target = superAdmin();
    $issued = app(SuperAdminTokenService::class)->issue($target);

    $this->artisan('super-admin:revoke-tokens', ['email' => $target->email])->assertSuccessful();

    $this->withToken($issued['access_token'])->getJson(saUrl('auth/me'))->assertUnauthorized();
});
