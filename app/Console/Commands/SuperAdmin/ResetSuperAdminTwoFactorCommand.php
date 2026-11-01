<?php

namespace App\Console\Commands\SuperAdmin;

use App\Console\Commands\SuperAdmin\Concerns\InteractsWithSuperAdmins;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Accounts\SuperAdminAccountService;
use Illuminate\Console\Command;

class ResetSuperAdminTwoFactorCommand extends Command
{
    use InteractsWithSuperAdmins;

    protected $signature = 'super-admin:reset-2fa {email : Email address of the super admin}';

    protected $description = "Reset a super admin's two-factor authentication (lost device) and sign them out everywhere";

    public function handle(SuperAdminAccountService $accounts): int
    {
        return $this->changeAccount(
            (string) $this->argument('email'),
            fn (SuperAdmin $admin) => $accounts->resetTwoFactor(null, $admin, 'Run from the command line', $this->actorLabel()),
            'Two-factor authentication reset. They must enrol it again at next sign-in.',
        );
    }
}
