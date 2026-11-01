<?php

namespace App\Console\Commands\Billing;

use App\Models\Billing\Subscription;
use App\Notifications\Billing\TrialEndingNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Reminds a workspace before its trial converts to a paid charge. Runs
 * daily and catches every trial whose `trial_ends_at` falls inside the
 * configured warning window, sending the reminder exactly once per trial
 * via `trial_ending_notified_at`.
 */
class NotifyTrialEndingCommand extends Command
{
    protected $signature = 'billing:notify-trial-ending';

    protected $description = 'Notifies workspaces whose trial is about to end and has not been announced yet.';

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $warningDays = (int) config('billing.trial_ending_warning_days', 3);

        $ending = Subscription::query()
            ->where('stripe_status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->whereNull('trial_ending_notified_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays($warningDays)])
            ->with('workspace')
            ->get();

        foreach ($ending as $subscription) {
            $workspace = $subscription->workspace;

            if ($workspace === null) {
                continue;
            }

            $this->dispatcher->dispatch(
                $this->dispatcher->ownersAndAdmins($workspace),
                new TrialEndingNotification($workspace, $subscription->trial_ends_at),
            );

            $subscription->markTrialEndingNotified();
        }

        $this->info("Notified {$ending->count()} workspace(s) about a trial ending soon.");

        return self::SUCCESS;
    }
}
