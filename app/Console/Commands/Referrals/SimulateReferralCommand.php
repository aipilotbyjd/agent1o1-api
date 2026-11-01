<?php

namespace App\Console\Commands\Referrals;

use App\Enums\Referrals\ReferralTrigger;
use App\Models\Billing\Plan;
use App\Models\Referrals\ReferralProgram;
use App\Services\Referrals\ReferralSimulator;
use App\Services\Referrals\TriggerContext;
use Illuminate\Console\Command;

class SimulateReferralCommand extends Command
{
    protected $signature = 'referrals:simulate
        {program : Program slug}
        {trigger : signup_verified, activated, first_payment, repeat_payment or milestone}
        {--payment-cents= : Payment amount, excluding tax}
        {--plan= : Plan slug the payment is for}
        {--interval= : monthly, quarterly, yearly or lifetime}
        {--source= : subscription, credit_pack or lifetime}
        {--sequence= : Which payment this is (1 = first)}
        {--converted= : Converted referrals, for milestone rules}
        {--multiplier=1 : The referral code\'s reward multiplier}';

    protected $description = 'Shows which rewards a program would give for an event, without writing anything.';

    public function handle(ReferralSimulator $simulator): int
    {
        $program = ReferralProgram::query()->where('slug', $this->argument('program'))->first();
        $trigger = ReferralTrigger::tryFrom($this->argument('trigger'));

        if ($program === null || $trigger === null || $trigger === ReferralTrigger::Manual) {
            $this->error('Unknown program slug or trigger.');

            return self::FAILURE;
        }

        $context = TriggerContext::fromArray(array_filter([
            'payment_cents' => $this->option('payment-cents'),
            'plan_id' => $this->option('plan') !== null ? Plan::query()->where('slug', $this->option('plan'))->value('id') : null,
            'billing_interval' => $this->option('interval'),
            'payment_source' => $this->option('source'),
            'payment_sequence' => $this->option('sequence'),
            'converted_referrals' => $this->option('converted'),
        ], fn ($value) => $value !== null));

        $results = $simulator->simulate($program, $trigger, $context, (float) $this->option('multiplier'));

        $this->table(
            ['Rule', 'Applies', 'Terms', 'Would be'],
            collect($results)->map(fn (array $result): array => [
                $result['name'],
                $result['applies'] ? 'yes' : 'no: '.$result['reason'],
                $result['description'],
                $result['applies'] ? $result['initial_status'] : '-',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
