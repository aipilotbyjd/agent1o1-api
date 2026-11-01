<?php

namespace App\Listeners\Referrals;

use App\Enums\Queue;
use App\Enums\Referrals\ReferralActivationEvent;
use App\Enums\Referrals\ReferralStatus;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Models\Agents\AgentSession;
use App\Models\Referrals\Referral;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Services\Referrals\ReferralLifecycle;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Fires the `activated` trigger once a referred workspace has completed
 * enough real work: what counts (workflow runs, agent sessions, or either),
 * how many, and within how long of signup are all program settings.
 */
class CheckReferralActivation implements ShouldQueue
{
    public string $queue = Queue::Billing->value;

    public function __construct(private readonly ReferralLifecycle $lifecycle) {}

    public function handle(RunCompleted $event): void
    {
        $run = $event->run->loadMissing('runnable');

        if (! $run->runnable instanceof Workflow && ! $run->runnable instanceof AgentSession) {
            return;
        }

        $referral = Referral::query()
            ->where('referred_workspace_id', $run->workspace_id)
            ->whereIn('status', [ReferralStatus::Pending, ReferralStatus::Verified])
            ->with('program')
            ->first();

        $program = $referral?->program;

        if ($referral === null || $program === null || $referral->created_at->copy()->addDays($program->activation_window_days)->isPast()) {
            return;
        }

        $types = $this->countedTypes($program->activation_event);

        if (! in_array($run->runnable::class, $types, true)) {
            return;
        }

        $completed = Run::query()
            ->where('workspace_id', $run->workspace_id)
            ->where('status', RunStatus::Completed)
            ->whereIn('runnable_type', $types)
            ->where('created_at', '>=', $referral->created_at)
            ->count();

        if ($completed >= $program->activation_min_count) {
            $this->lifecycle->markActivated($referral);
        }
    }

    /**
     * The `runnable_type` values that count, in both forms the column holds:
     * the morph-map alias (runs created through a relation) and the class
     * name (runs created directly): see `Run::totalCreditsUsed()`.
     *
     * @return list<string>
     */
    private function countedTypes(ReferralActivationEvent $event): array
    {
        $classes = match ($event) {
            ReferralActivationEvent::FirstSuccessfulRun => [Workflow::class],
            ReferralActivationEvent::FirstAgentSession => [AgentSession::class],
            ReferralActivationEvent::RunOrAgentSession => [Workflow::class, AgentSession::class],
        };

        return collect($classes)
            ->flatMap(fn (string $class): array => [$class, (new $class)->getMorphClass()])
            ->unique()
            ->values()
            ->all();
    }
}
