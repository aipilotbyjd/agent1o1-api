<?php

namespace App\Console\Commands\SuperAdmin;

use App\Console\Commands\SuperAdmin\Concerns\InteractsWithSuperAdmins;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Accounts\SuperAdminAccountService;
use Illuminate\Console\Command;

class EnableSuperAdminCommand extends Command
{
    use InteractsWithSuperAdmins;

    protected $signature = 'super-admin:enable {email : Email address of the super admin}';

    protected $description = 'Re-enable a disabled super admin and clear any sign-in lock';

    public function handle(SuperAdminAccountService $accounts): int
    {
        return $this->changeAccount(
            (string) $this->argument('email'),
            fn (SuperAdmin $admin) => $accounts->enable(null, $admin, 'Run from the command line', $this->actorLabel()),
            'Account enabled.',
        );
    }
}
