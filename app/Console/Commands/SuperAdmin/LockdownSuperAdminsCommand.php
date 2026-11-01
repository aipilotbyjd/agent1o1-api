<?php

namespace App\Console\Commands\SuperAdmin;

use App\Console\Commands\SuperAdmin\Concerns\InteractsWithSuperAdmins;
use App\Services\SuperAdmin\Alerts\SuperAdminAlerter;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\SuperAdmin\Auth\SuperAdminTokenService;
use Illuminate\Console\Command;

/**
 * The emergency button for a suspected compromise: signs every super admin
 * out of every session at once, so nobody holding a stolen token keeps access.
 * Accounts stay as they are and can sign in again with their password and
 * authenticator; to also shut the API itself, set `SUPER_ADMIN_ENABLED=false`.
 */
class LockdownSuperAdminsCommand extends Command
{
    use InteractsWithSuperAdmins;

    protected $signature = 'super-admin:lockdown {--reason= : Why (kept in the audit log)}';

    protected $description = 'Signs every super admin out of every session, for a suspected compromise.';

    public function handle(SuperAdminTokenService $tokens, SuperAdminAuditLogger $audit, SuperAdminAlerter $alerter): int
    {
        if (! $this->superAdminEnabled()) {
            return self::FAILURE;
        }

        $ended = $tokens->revokeEverything();
        $reason = $this->option('reason') ?: 'Lockdown from the command line';

        $audit->record(null, 'accounts.lockdown', after: ['sessions_ended' => $ended], reason: $reason, actorLabel: $this->actorLabel());
        $alerter->raise('lockdown', "Every Super Admin session was ended: {$reason}", ['by' => $this->actorLabel(), 'sessions_ended' => $ended]);

        $this->components->info("Ended {$ended} session(s). Every super admin has to sign in again.");

        return self::SUCCESS;
    }
}
