<?php

namespace App\Console\Commands\SuperAdmin;

use App\Console\Commands\SuperAdmin\Concerns\InteractsWithSuperAdmins;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Accounts\SuperAdminAccountService;
use Illuminate\Console\Command;

class DisableSuperAdminCommand extends Command
{
    use InteractsWithSuperAdmins;

    protected $signature = 'super-admin:disable {email : Email address of the super admin}';

    protected $description = 'Disable a super admin and sign them out everywhere';

    public function handle(SuperAdminAccountService $accounts): int
    {
        return $this->changeAccount(
            (string) $this->argument('email'),
            fn (SuperAdmin $admin) => $accounts->disable(null, $admin, 'Run from the command line', $this->actorLabel()),
            'Account disabled and all sessions revoked.',
        );
    }
}
