<?php

namespace App\Console\Commands\SuperAdmin;

use App\Console\Commands\SuperAdmin\Concerns\InteractsWithSuperAdmins;
use App\Services\SuperAdmin\Alerts\SuperAdminAlerter;
use App\Services\SuperAdmin\Audit\AuditChainVerifier;
use Illuminate\Console\Command;

/**
 * Checks the audit log's chain end to end and fails (and raises an alert)
 * if anything was removed, changed or slipped in. Run daily by the
 * scheduler. Keep the `head` it prints somewhere the database's writers cannot
 * reach and pass it back as `--expect-hash` to also catch entries cut off the
 * end. See `AuditChainVerifier`.
 */
class VerifySuperAdminAuditLogCommand extends Command
{
    use InteractsWithSuperAdmins;

    protected $signature = 'super-admin:audit-verify {--expect-hash= : The head hash you recorded earlier}';

    protected $description = 'Checks that the Super Admin audit log has not been tampered with.';

    public function handle(AuditChainVerifier $verifier, SuperAdminAlerter $alerter): int
    {
        if (! $this->superAdminEnabled()) {
            return self::FAILURE;
        }

        $result = $verifier->verify($this->option('expect-hash') ?: null);

        $this->line("Checked {$result['checked']} entr".($result['checked'] === 1 ? 'y' : 'ies').'.');

        if ($result['intact']) {
            $this->components->info('The audit log is intact.');
            $this->line('head: '.($result['head_sequence'] ?? '-').' '.($result['head_hash'] ?? ''));

            return self::SUCCESS;
        }

        $this->components->error($result['break']['message']);

        $alerter->raise('audit_tampering', 'The Super Admin audit log failed its integrity check: '.$result['break']['message'], [
            'reason' => $result['break']['reason'],
            'sequence' => $result['break']['sequence'],
        ]);

        return self::FAILURE;
    }
}
