<?php

namespace App\Console\Commands\SuperAdmin;

use App\Console\Commands\SuperAdmin\Concerns\InteractsWithSuperAdmins;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Accounts\SuperAdminAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Provisions a super admin. There is no signup: this command (or an owner
 * through the accounts API) is the only way an account comes to exist.
 */
class CreateSuperAdminCommand extends Command
{
    use InteractsWithSuperAdmins;

    protected $signature = 'super-admin:create {email : Email address of the new super admin} {--name= : Display name (defaults to the part before the @)} {--role=viewer : owner, operator, finance, support or viewer}';

    protected $description = 'Create a super admin with a one-time temporary password';

    public function handle(SuperAdminAccountService $accounts): int
    {
        if (! $this->superAdminEnabled()) {
            return self::FAILURE;
        }

        $role = SuperAdminRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->components->error('Unknown role. Use: '.implode(', ', array_column(SuperAdminRole::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('That is not a valid email address.');

            return self::FAILURE;
        }

        if (SuperAdmin::query()->where('email', $email)->exists()) {
            $this->components->error("A super admin with the email {$email} already exists.");

            return self::FAILURE;
        }

        $result = $accounts->create(
            null,
            [
                'name' => $this->option('name') ?: Str::before($email, '@'),
                'email' => $email,
                'role' => $role,
            ],
            'Created from the command line',
            $this->actorLabel(),
        );

        $this->components->info("Created {$role->label()} {$email}.");
        $this->line('Temporary password (shown once): <comment>'.$result['temporary_password'].'</comment>');
        $this->line('They must change it at first sign-in, then enrol two-factor authentication.');

        return self::SUCCESS;
    }
}
