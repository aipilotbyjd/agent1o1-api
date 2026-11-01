<?php

namespace App\Services\Billing;

use App\Enums\Notifications\AlertSeverity;
use App\Exceptions\CostLimitReachedException;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\WorkspaceSuspendedException;
use App\Models\Workspaces\Workspace;
use App\Notifications\Billing\CreditsExhaustedNotification;
use App\Notifications\Billing\OverageCapReachedNotification;
use App\Services\Notifications\AdminAlerts;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\Cache;

/**
 * The pre-flight balance check. Credits are metered *after* the fact, on
 * `RunCompleted`, without a gate a workspace at zero still executed its
 * whole DAG (and paid for the LLM tokens) before the charge was even
 * attempted. This refuses the run up front instead, mapping to a 402 through
 * `bootstrap/app.php`.
 *
 * The check is deliberately coarse: a run's true cost isn't knowable until
 * its nodes have executed, so this only asserts the workspace can afford to
 * begin. A run that overruns the remaining balance still completes and is
 * billed in full (see `DeductCreditsAction`'s overdraft mode): the *next*
 * run is the one that gets refused.
 *
 * A workspace that opted into credit overage is not refused at zero: the
 * allowance it may still spend on top is `CreditOverage`'s to answer, and
 * only once *that* is gone does this refuse. Both paths raise the
 * `usage.limit_exhausted` admin alert, but they tell the customer different
 * things: a spent allowance resets with the period, a spent overage cap
 * does not.
 */
class CreditGate
{
    /**
     * Cost of the cheapest possible unit of billable work: one node run or
     * one agent turn that consumes no tokens. A workspace that can't cover
     * this can't usefully start anything.
     */
    private const int MINIMUM_CREDITS_TO_START = 1;

    /**
     * How long a workspace goes without a repeat "you're out of credits"
     * notification: independent of `admin_alerts.enabled`, since that flag
     * governs operator alerting, not what customers are told.
     */
    private const int NOTIFICATION_THROTTLE_SECONDS = 3600;

    public function __construct(
        private readonly AdminAlerts $adminAlerts,
        private readonly NotificationDispatcher $dispatcher,
        private readonly CreditOverage $overage,
        private readonly CostGuard $costGuard,
    ) {}

    /**
     * @throws InsufficientCreditsException
     * @throws CostLimitReachedException when an AI cost guardrail is reached
     *                                   (see `CostGuard`)
     */
    public function assertCanStartRun(Workspace $workspace): void
    {
        if ($workspace->isSuspended()) {
            throw new WorkspaceSuspendedException;
        }

        $available = $workspace->availableCredits();

        if ($available !== null
            && $available < self::MINIMUM_CREDITS_TO_START
            && ! $this->overage->canAbsorb($workspace, self::MINIMUM_CREDITS_TO_START)) {
            $this->alertCreditsExhausted($workspace, $available);

            throw new InsufficientCreditsException(self::MINIMUM_CREDITS_TO_START, $available);
        }

        $this->costGuard->assertCanStart($workspace);
    }

    /**
     * `assertCanStartRun()` plus the plan's limit on workflow runs in flight.
     *
     * @throws InsufficientCreditsException
     * @throws CostLimitReachedException
     */
    public function assertCanStartWorkflowRun(Workspace $workspace): void
    {
        $this->assertCanStartRun($workspace);
        $this->costGuard->assertCanStartWorkflowRun($workspace);
    }

    /**
     * The workspace can no longer start work of any kind until its allowance
     * resets, its overage cap is raised, or it buys top-up credits, so this
     * is graded Critical. Throttled per workspace: every refused run
     * re-detects the same condition.
     */
    private function alertCreditsExhausted(Workspace $workspace, int $available): void
    {
        $cappedByOverage = $this->overage->isEnabledFor($workspace);

        $this->adminAlerts->raise(
            key: 'usage.limit_exhausted',
            title: $cappedByOverage
                ? "{$workspace->name} is blocked: overage cap reached"
                : "{$workspace->name} is blocked: out of credits",
            body: $cappedByOverage
                ? 'New workflow runs and agent turns are being refused until the overage cap is raised or the billing period resets.'
                : 'New workflow runs and agent turns are being refused until the plan allowance resets or top-up credits are purchased.',
            context: [
                'workspace_id' => $workspace->id,
                'credits_available' => $available,
                'overage_enabled' => $cappedByOverage,
            ],
            severity: AlertSeverity::Critical,
            throttleKey: "usage.limit_exhausted:{$workspace->id}",
        );

        $this->notifyBlocked($workspace, $cappedByOverage);
    }

    /**
     * Told once per throttle window, same as the admin alert above but on
     * its own key: this must fire even when `admin_alerts.enabled` is off,
     * since that flag is an operator kill switch, not a customer-facing one.
     *
     * A workspace running on overage gets the cap-reached message instead:
     * "you are out of credits" would be misleading advice when it has
     * already agreed to pay past the allowance.
     *
     * Suppressed entirely by the workspace's own "Out of Credits
     * Notification" switch (Gumloop's, on by default). The admin alert above
     * is not: an operator still wants to know a tenant is blocked, whatever
     * the tenant chose to be emailed about.
     */
    private function notifyBlocked(Workspace $workspace, bool $cappedByOverage): void
    {
        if (! $workspace->out_of_credits_notification_enabled) {
            return;
        }

        $claimed = Cache::add(
            'credit-gate:exhausted-notified:'.$workspace->id,
            true,
            self::NOTIFICATION_THROTTLE_SECONDS,
        );

        if (! $claimed) {
            return;
        }

        $period = $workspace->currentUsagePeriod();

        $notification = $cappedByOverage
            ? new OverageCapReachedNotification(
                $workspace,
                $period->overage_credits_used,
                $this->overage->effectiveLimitFor($workspace) ?? $period->overage_credits_used,
            )
            : new CreditsExhaustedNotification($workspace);

        $this->dispatcher->dispatch(
            $this->dispatcher->ownersAndAdmins($workspace),
            $notification,
        );
    }
}
