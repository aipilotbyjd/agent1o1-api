<?php

namespace App\Services\Onboarding;

use App\Enums\Onboarding\OnboardingStep;
use App\Models\User;
use App\Notifications\Onboarding\FinishOnboardingReminderNotification;
use Illuminate\Database\Eloquent\Builder;

/**
 * One email, once, to a user who stopped part way through onboarding: a day
 * after they last made progress, telling them how close their first
 * automation is. Users who finished, closed the wizard, opted out of
 * marketing email or are suspended are left alone, and so is anyone who
 * signed up more than a week ago, so turning this on does not email every
 * old account at once.
 */
final class OnboardingReminders
{
    /**
     * How long after the last bit of progress the user counts as gone.
     */
    public const int IDLE_HOURS = 24;

    /**
     * How far back signups are reminded at all.
     */
    public const int MAX_ACCOUNT_AGE_DAYS = 7;

    /**
     * The steps between a new user and their first automation. Telling us
     * their role helps, but it does not stand in the way.
     *
     * @var array<int, OnboardingStep>
     */
    private const array STEPS_TO_FIRST_AUTOMATION = [
        OnboardingStep::ConnectAi,
        OnboardingStep::ChooseTemplate,
        OnboardingStep::FirstRun,
    ];

    public function __construct(private readonly OnboardingService $onboarding) {}

    /**
     * Sends every reminder that is due and returns how many went out.
     */
    public function sendDue(): int
    {
        $sent = 0;

        $this->due()->chunkById(100, function ($users) use (&$sent): void {
            foreach ($users as $user) {
                // Claimed before sending, so a retried or overlapping run cannot send it twice.
                $claimed = User::query()
                    ->whereKey($user->getKey())
                    ->whereNull('onboarding_reminder_sent_at')
                    ->update(['onboarding_reminder_sent_at' => now()]);

                if ($claimed === 0) {
                    continue;
                }

                $user->notify(new FinishOnboardingReminderNotification($this->stepsToFirstAutomation($user)));
                $sent++;
            }
        });

        return $sent;
    }

    /**
     * How many of the steps that stand before a first automation are still
     * open. Never less than one: someone who did all of them but never
     * pressed Finish still has that left.
     */
    public function stepsToFirstAutomation(User $user): int
    {
        $completed = collect($this->onboarding->state($user)['steps'])
            ->where('completed', true)
            ->pluck('key');

        $open = collect(self::STEPS_TO_FIRST_AUTOMATION)
            ->reject(fn (OnboardingStep $step): bool => $completed->contains($step->value))
            ->count();

        return max(1, $open);
    }

    /**
     * Progress saves to the user row, so its `updated_at` is when they last
     * moved forward.
     *
     * @return Builder<User>
     */
    private function due(): Builder
    {
        return User::query()
            ->whereNull('onboarding_completed_at')
            ->whereNull('onboarding_dismissed_at')
            ->whereNull('onboarding_reminder_sent_at')
            ->whereNull('marketing_opted_out_at')
            ->whereNull('suspended_at')
            ->whereNotNull('email_verified_at')
            ->where('created_at', '>=', now()->subDays(self::MAX_ACCOUNT_AGE_DAYS))
            ->where('updated_at', '<=', now()->subHours(self::IDLE_HOURS));
    }
}
