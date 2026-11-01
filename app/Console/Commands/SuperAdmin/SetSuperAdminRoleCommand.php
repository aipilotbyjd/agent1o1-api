<?php

namespace App\Console\Commands\SuperAdmin;

use App\Console\Commands\SuperAdmin\Concerns\InteractsWithSuperAdmins;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Accounts\SuperAdminAccountService;
use Illuminate\Console\Command;

class SetSuperAdminRoleCommand extends Command
{
    use InteractsWithSuperAdmins;

    protected $signature = 'super-admin:set-role {email : Email address of the super admin} {role : owner, operator, finance, support or viewer}';

    protected $description = 'Emergency role change from the command line (audited, last-owner rule still applies)';

    public function handle(SuperAdminAccountService $accounts): int
    {
        $role = SuperAdminRole::tryFrom((string) $this->argument('role'));

        if ($role === null) {
            $this->components->error('Unknown role. Use: '.implode(', ', array_column(SuperAdminRole::cases(), 'value')).'.');

            return self::FAILURE;
        }

        return $this->changeAccount(
            (string) $this->argument('email'),
            fn (SuperAdmin $admin) => $accounts->changeRole(null, $admin, $role, 'Run from the command line', $this->actorLabel()),
            "Role set to {$role->label()}.",
        );
    }
}
