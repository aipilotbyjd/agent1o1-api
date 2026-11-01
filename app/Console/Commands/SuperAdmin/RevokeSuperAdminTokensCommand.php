<?php

namespace App\Console\Commands\SuperAdmin;

use App\Console\Commands\SuperAdmin\Concerns\InteractsWithSuperAdmins;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Accounts\SuperAdminAccountService;
use Illuminate\Console\Command;

class RevokeSuperAdminTokensCommand extends Command
{
    use InteractsWithSuperAdmins;

    protected $signature = 'super-admin:revoke-tokens {email : Email address of the super admin}';

    protected $description = 'Sign a super admin out of every session';

    public function handle(SuperAdminAccountService $accounts): int
    {
        return $this->changeAccount(
            (string) $this->argument('email'),
            fn (SuperAdmin $admin) => $accounts->revokeSessions(null, $admin, 'Run from the command line', $this->actorLabel()),
            'All sessions revoked.',
        );
    }
}
