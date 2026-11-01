<?php

namespace App\Services\Billing;

use App\Enums\Billing\PlanLimit;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Workspaces\Workspace;

/**
 * The resource-cap check, sibling to `CreditGate`. Credits meter how much
 * work a workspace may *do*; this caps how much it may *own*: the tier
 * differentiator `Plan.limits` sells but nothing previously enforced.
 *
 * Deliberately fails open twice over. A workspace whose `currentPlan()` is
 * null (a half-seeded install, per `config/billing.php`) is never refused,
 * and a plan that doesn't mention a limit is treated as unlimited rather
 * than as zero, so adding a `PlanLimit` case can't retroactively lock every
 * existing workspace out of creating anything.
 *
 * The check runs on create only. A workspace pushed over a cap by a
 * *downgrade* keeps everything it already has and can still edit it; it just
 * can't add more until it's back under.
 */
class PlanLimitGate
{
    /**
     * @param  int  $count  How many are about to be created. Batch callers
     *                      (a template collection instantiating a whole pack)
     *                      must assert the whole batch up front: checking one
     *                      at a time leaves a partially created batch behind
     *                      when the cap is hit mid-loop.
     *
     * @throws PlanLimitExceededException
     */
    public function assertCanCreate(Workspace $workspace, PlanLimit $limit, int $count = 1): void
    {
        $max = $workspace->currentPlan()?->limit($limit);

        if ($max === null) {
            return;
        }

        $used = $this->usage($workspace, $limit);

        if ($used + $count <= $max) {
            return;
        }

        throw new PlanLimitExceededException($limit, $max, $used);
    }

    /**
     * How much of a limit the workspace is currently consuming.
     *
     * `Workflow`, `Agent` and `WorkspaceMember` all soft-delete, so these
     * counts exclude trashed rows without asking: deleting a workflow frees
     * its slot. Seats additionally count unaccepted invitations: without that
     * a two-seat workspace could hold fifty pending invites and blow past its
     * cap the moment they were accepted.
     */
    public function usage(Workspace $workspace, PlanLimit $limit): int
    {
        return match ($limit) {
            PlanLimit::Workflows => $workspace->workflows()->visible()->count(),
            PlanLimit::Agents => $workspace->agents()->count(),
            PlanLimit::Members => $workspace->members()->count()
                + $workspace->invitations()->pending()->count(),
            PlanLimit::Pages => $workspace->workflowPages()->count(),
            // Switched-off triggers are free: only what's actually listening counts.
            PlanLimit::ActiveTriggers => $workspace->triggers()->where('is_active', true)->count(),
        };
    }
}
