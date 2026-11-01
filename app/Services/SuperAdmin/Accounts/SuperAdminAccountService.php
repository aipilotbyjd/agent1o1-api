<?php

namespace App\Services\SuperAdmin\Accounts;

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Alerts\SuperAdminAlerter;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\SuperAdmin\Auth\SuperAdminTokenService;
use App\Services\SuperAdmin\Auth\SuperAdminTwoFactorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages Super Admin accounts and their roles. Called from the accounts API
 * (`$actor` is the owner making the change) and from the artisan commands
 * (`$actor` is null and `$actorLabel` says who ran it).
 *
 * The rules live here, not in the controllers, so the command line cannot
 * break them either:
 *  - at least one active owner always exists;
 *  - nobody changes their own role, disables themselves or resets their own
 *    two-factor authentication: another owner (or the command line) must.
 *
 * (There is no "cannot grant above your own role" check: only owners manage
 * accounts, and owner is the top role.)
 */
class SuperAdminAccountService
{
    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly SuperAdminTokenService $tokens,
        private readonly SuperAdminTwoFactorService $twoFactor,
        private readonly SuperAdminAlerter $alerter,
    ) {}

    /**
     * Provisions an account with a one-time temporary password. The password
     * counts as expired until it is changed, so the first sign-in has to set a
     * new one and then enrol two-factor authentication.
     *
     * @param  array{name: string, email: string, role: SuperAdminRole, allowed_ips?: array<int, string>|null}  $data
     * @return array{admin: SuperAdmin, temporary_password: string}
     */
    public function create(?SuperAdmin $actor, array $data, ?string $reason = null, ?string $actorLabel = null): array
    {
        $temporaryPassword = Str::password(24);

        $admin = DB::transaction(function () use ($actor, $data, $temporaryPassword, $reason, $actorLabel): SuperAdmin {
            $admin = new SuperAdmin([
                'name' => $data['name'],
                'email' => Str::lower(trim($data['email'])),
                'password' => $temporaryPassword,
                'allowed_ips' => $data['allowed_ips'] ?? null,
            ]);

            $admin->forceFill([
                'role' => $data['role'],
                'created_by' => $actor?->id,
                'password_changed_at' => null,
            ])->save();

            $this->audit->record($actor, 'accounts.created', $admin, null, [
                'email' => $admin->email,
                'role' => $admin->role->value,
            ], $reason, $actorLabel);

            return $admin;
        });

        $this->alerter->raise('account_created', 'A super admin account was created.', [
            'email' => $admin->email,
            'role' => $admin->role->value,
            'by' => $actor?->email ?? $actorLabel,
        ]);

        return ['admin' => $admin, 'temporary_password' => $temporaryPassword];
    }

    /**
     * @param  array{name?: string, allowed_ips?: array<int, string>|null}  $data
     */
    public function update(?SuperAdmin $actor, SuperAdmin $target, array $data, ?string $actorLabel = null): SuperAdmin
    {
        $original = $target->getAttributes();

        $target->fill($data)->save();

        $this->audit->recordChanges($actor, 'accounts.updated', $target, $original, actorLabel: $actorLabel);

        return $target;
    }

    public function changeRole(?SuperAdmin $actor, SuperAdmin $target, SuperAdminRole $role, ?string $reason = null, ?string $actorLabel = null): SuperAdmin
    {
        $this->assertNotSelf($actor, $target, 'change your own role');

        $previous = $target->role;

        if ($previous === $role) {
            return $target;
        }

        if ($previous === SuperAdminRole::Owner) {
            $this->assertAnotherActiveOwner($target);
        }

        DB::transaction(function () use ($actor, $target, $role, $previous, $reason, $actorLabel): void {
            $target->forceFill(['role' => $role])->save();

            $this->audit->record(
                $actor,
                'accounts.role_changed',
                $target,
                ['role' => $previous->value],
                ['role' => $role->value],
                $reason,
                $actorLabel,
            );
        });

        // Losing power ends the sessions that were issued under the old role:
        // all of them when leaving owner, the ability to refresh otherwise.
        if ($previous === SuperAdminRole::Owner) {
            $this->tokens->revokeAllFor($target);
        } elseif (count($role->abilities()) < count($previous->abilities())) {
            $this->tokens->revokeRefreshTokensFor($target);
        }

        $this->alerter->raise('role_changed', 'A super admin role was changed.', [
            'email' => $target->email,
            'from' => $previous->value,
            'to' => $role->value,
            'by' => $actor?->email ?? $actorLabel,
        ]);

        return $target;
    }

    public function disable(?SuperAdmin $actor, SuperAdmin $target, ?string $reason = null, ?string $actorLabel = null): SuperAdmin
    {
        $this->assertNotSelf($actor, $target, 'disable your own account');

        if ($target->isDisabled()) {
            return $target;
        }

        if ($target->isOwner()) {
            $this->assertAnotherActiveOwner($target);
        }

        DB::transaction(function () use ($actor, $target, $reason, $actorLabel): void {
            $target->forceFill(['disabled_at' => now()])->save();

            $this->audit->record($actor, 'accounts.disabled', $target, ['disabled' => false], ['disabled' => true], $reason, $actorLabel);
        });

        $this->tokens->revokeAllFor($target);

        $this->alerter->raise('account_disabled', 'A super admin account was disabled.', [
            'email' => $target->email,
            'by' => $actor?->email ?? $actorLabel,
        ]);

        return $target;
    }

    public function enable(?SuperAdmin $actor, SuperAdmin $target, ?string $reason = null, ?string $actorLabel = null): SuperAdmin
    {
        if (! $target->isDisabled()) {
            return $target;
        }

        DB::transaction(function () use ($actor, $target, $reason, $actorLabel): void {
            $target->forceFill([
                'disabled_at' => null,
                'failed_login_count' => 0,
                'locked_until' => null,
            ])->save();

            $this->audit->record($actor, 'accounts.enabled', $target, ['disabled' => true], ['disabled' => false], $reason, $actorLabel);
        });

        return $target;
    }

    public function resetTwoFactor(?SuperAdmin $actor, SuperAdmin $target, ?string $reason = null, ?string $actorLabel = null): SuperAdmin
    {
        $this->assertNotSelf($actor, $target, 'reset your own two-factor authentication');

        DB::transaction(function () use ($actor, $target, $reason, $actorLabel): void {
            $this->twoFactor->reset($target);

            $this->audit->record($actor, 'accounts.two_factor_reset', $target, ['two_factor' => true], ['two_factor' => false], $reason, $actorLabel);
        });

        $this->tokens->revokeAllFor($target);

        $this->alerter->raise('two_factor_reset', 'A super admin had two-factor authentication reset.', [
            'email' => $target->email,
            'by' => $actor?->email ?? $actorLabel,
        ]);

        return $target;
    }

    public function revokeSessions(?SuperAdmin $actor, SuperAdmin $target, ?string $reason = null, ?string $actorLabel = null): SuperAdmin
    {
        $this->tokens->revokeAllFor($target);

        $this->audit->record($actor, 'accounts.sessions_revoked', $target, reason: $reason, actorLabel: $actorLabel);

        return $target;
    }

    private function assertNotSelf(?SuperAdmin $actor, SuperAdmin $target, string $action): void
    {
        abort_if($actor !== null && $actor->is($target), 409, "You cannot {$action}. Ask another owner.");
    }

    private function assertAnotherActiveOwner(SuperAdmin $target): void
    {
        $anotherOwnerExists = SuperAdmin::query()
            ->where('role', SuperAdminRole::Owner->value)
            ->whereNull('disabled_at')
            ->whereKeyNot($target->getKey())
            ->exists();

        abort_unless($anotherOwnerExists, 409, 'At least one active owner must remain.');
    }
}
