<?php

namespace App\Console\Commands\Onboarding;

use App\Services\Onboarding\OnboardingReminders;
use Illuminate\Console\Command;

/**
 * Emails users who left onboarding part way a day ago, once each.
 */
class SendOnboardingRemindersCommand extends Command
{
    protected $signature = 'onboarding:send-reminders';

    protected $description = 'Email users who left onboarding part way, once, a day after their last progress.';

    public function handle(OnboardingReminders $reminders): int
    {
        $count = $reminders->sendDue();

        $this->info("Sent {$count} onboarding reminder(s).");

        return self::SUCCESS;
    }
}
