<?php

namespace App\Console\Commands\Onboarding;

use App\Services\Onboarding\LifecycleEmails;
use Illuminate\Console\Command;

/**
 * Sends the emails of a new account's first weeks as they come due.
 */
class SendLifecycleEmailsCommand extends Command
{
    protected $signature = 'onboarding:send-lifecycle-emails';

    protected $description = 'Send the welcome, nudge, recap and win-back emails of a new account\'s first weeks, once each, while they still apply.';

    public function handle(LifecycleEmails $emails): int
    {
        $count = $emails->sendDue();

        $this->info("Sent {$count} lifecycle email(s).");

        return self::SUCCESS;
    }
}
