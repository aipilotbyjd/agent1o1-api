<?php

namespace App\Console\Commands\Referrals;

use App\Services\Referrals\ReferralStats;
use Illuminate\Console\Command;

class ReferralStatsCommand extends Command
{
    protected $signature = 'referrals:stats {--days=30 : How many days back to count}';

    protected $description = 'Shows how the referral program is doing.';

    public function handle(ReferralStats $stats): int
    {
        $days = max(1, (int) $this->option('days'));
        $numbers = $stats->platform($days);
        $percent = fn (float|int $ratio): string => round($ratio * 100, 1).'%';

        $this->info("Referral program: last {$days} days");

        $this->table(['Metric', 'Value'], [
            ['Link visits', $numbers['visits']],
            ['Signups (rejected)', "{$numbers['signups']} ({$numbers['rejected']})"],
            ['Activation rate', $percent($numbers['activation_rate'])],
            ['Conversion rate', $percent($numbers['conversion_rate'])],
            ['Credits issued', number_format($numbers['credits_issued'])],
            ['Free plan days issued', number_format($numbers['plan_days_issued'])],
            ['Invoice credit issued', '$'.number_format($numbers['invoice_credit_cents_issued'] / 100, 2)],
            ['Credits clawed back', number_format($numbers['credits_clawed_back'])],
            ['Rewards awaiting approval', $numbers['rewards_awaiting_approval']],
            ['Rewards on hold', $numbers['rewards_pending']],
            ['Active free-plan grants', $numbers['active_referral_plan_grants']],
        ]);

        if (count($numbers['top_referrers']) > 0) {
            $this->table(
                ['Top referrer', 'Paying referrals'],
                collect($numbers['top_referrers'])->map(fn (array $row): array => [$row['email'] ?? $row['user_id'], $row['converted']])->all(),
            );
        }

        return self::SUCCESS;
    }
}
