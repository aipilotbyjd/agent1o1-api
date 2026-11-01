<?php

namespace App\Console\Commands\SuperAdmin\Concerns;

use App\Models\SuperAdmin\SuperAdmin;
use Closure;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Shared behaviour of the `super-admin:*` commands: every one refuses to run
 * while the master switch is off, and the rules in `SuperAdminAccountService`
 * (last owner, no self-service) apply to the command line too.
 */
trait InteractsWithSuperAdmins
{
    protected function superAdminEnabled(): bool
    {
        if (config('super_admin.enabled')) {
            return true;
        }

        $this->components->error('Super Admin is disabled. Set SUPER_ADMIN_ENABLED=true to use this command.');

        return false;
    }

    /**
     * Who the audit log should name when the command line made the change.
     */
    protected function actorLabel(): string
    {
        return 'cli:'.get_current_user();
    }

    protected function findSuperAdmin(string $email): ?SuperAdmin
    {
        $admin = SuperAdmin::query()->where('email', Str::lower(trim($email)))->first();

        if ($admin === null) {
            $this->components->error("No super admin with the email {$email}.");
        }

        return $admin;
    }

    /**
     * Runs an account change, turning a refused rule (HTTP 409 from the
     * service) into a plain error message.
     *
     * @param  Closure(SuperAdmin): mixed  $change
     */
    protected function changeAccount(string $email, Closure $change, string $done): int
    {
        if (! $this->superAdminEnabled()) {
            return self::FAILURE;
        }

        $admin = $this->findSuperAdmin($email);

        if ($admin === null) {
            return self::FAILURE;
        }

        try {
            $change($admin);
        } catch (HttpException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($done);

        return self::SUCCESS;
    }
}
