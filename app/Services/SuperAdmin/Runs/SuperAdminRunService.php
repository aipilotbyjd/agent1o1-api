<?php

namespace App\Services\SuperAdmin\Runs;

use App\Actions\Workflows\CancelRunAction;
use App\Actions\Workflows\RetryRunAction;
use App\Exceptions\RunStateException;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;

/**
 * Retry and cancel for any workspace's runs. Uses the same actions as the
 * product (so the rules about what can be retried or cancelled are not
 * repeated here); a super admin is not a `User`, so no user is recorded as
 * having triggered it: the audit log names the super admin instead.
 */
class SuperAdminRunService
{
    public function __construct(
        private readonly RetryRunAction $retry,
        private readonly CancelRunAction $cancel,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function retry(SuperAdmin $actor, Run $run, string $reason): Run
    {
        try {
            $retried = $this->retry->execute($run);
        } catch (RunStateException $e) {
            abort(409, $e->getMessage());
        }

        $this->audit->record($actor, 'runs.retried', $run, null, ['new_run_id' => $retried->id], $reason);

        return $retried;
    }

    public function cancel(SuperAdmin $actor, Run $run, string $reason): Run
    {
        $previous = $run->status->value;

        try {
            $cancelled = $this->cancel->execute($run);
        } catch (RunStateException $e) {
            abort(409, $e->getMessage());
        }

        $this->audit->record($actor, 'runs.cancelled', $run, ['status' => $previous], ['status' => $cancelled->status->value], $reason);

        return $cancelled;
    }
}
