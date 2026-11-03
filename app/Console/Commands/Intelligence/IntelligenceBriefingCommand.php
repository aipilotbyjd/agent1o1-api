<?php

namespace App\Console\Commands\Intelligence;

use App\Notifications\Intelligence\WeeklyBriefingNotification;
use App\Services\Intelligence\WeeklyBriefing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Mails the weekly business briefing to `intelligence.briefing.recipients`,
 * or to the Super Admin alert recipients when none are set.
 */
class IntelligenceBriefingCommand extends Command
{
    protected $signature = 'intelligence:briefing';

    protected $description = 'Sends the weekly business briefing to the operators.';

    public function handle(WeeklyBriefing $briefing): int
    {
        $recipients = config('intelligence.briefing.recipients') ?: config('super_admin_alerts.recipients.mail', []);

        if ($recipients === []) {
            $this->warn('Nobody to send the briefing to: set INTELLIGENCE_BRIEFING_EMAILS.');

            return self::SUCCESS;
        }

        Notification::route('mail', $recipients)->notify(new WeeklyBriefingNotification($briefing->build()));

        $this->info('Briefing sent to '.count($recipients).' recipient(s).');

        return self::SUCCESS;
    }
}
