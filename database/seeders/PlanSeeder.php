<?php

namespace Database\Seeders;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Get started for free.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'limits' => [
                    Limit::CreditsMonthly->value => 1000,
                    Limit::Seats->value => 1,
                ],
                'features' => [
                    Feature::CreditPacks->value => false,
                    Feature::AnnualRollover->value => false,
                    Feature::AuditLogs->value => false,
                    Feature::ApiAccess->value => true,
                    Feature::Webhooks->value => false,
                ],
                'trial_days' => 0,
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For individuals getting serious.',
                'price_monthly' => 1200,
                'price_yearly' => 12000,
                'limits' => [
                    Limit::CreditsMonthly->value => 10000,
                    Limit::Seats->value => 3,
                ],
                'features' => [
                    Feature::CreditPacks->value => true,
                    Feature::AnnualRollover->value => false,
                    Feature::AuditLogs->value => false,
                    Feature::ApiAccess->value => true,
                    Feature::Webhooks->value => true,
                ],
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'For power users.',
                'price_monthly' => 2900,
                'price_yearly' => 29000,
                'limits' => [
                    Limit::CreditsMonthly->value => 50000,
                    Limit::Seats->value => 5,
                ],
                'features' => [
                    Feature::CreditPacks->value => true,
                    Feature::AnnualRollover->value => true,
                    Feature::AuditLogs->value => false,
                    Feature::ApiAccess->value => true,
                    Feature::Webhooks->value => true,
                ],
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Teams',
                'slug' => 'teams',
                'description' => 'For growing teams.',
                'price_monthly' => 7900,
                'price_yearly' => 79000,
                'limits' => [
                    Limit::CreditsMonthly->value => 200000,
                    Limit::Seats->value => 25,
                ],
                'features' => [
                    Feature::CreditPacks->value => true,
                    Feature::AnnualRollover->value => true,
                    Feature::AuditLogs->value => true,
                    Feature::ApiAccess->value => true,
                    Feature::Webhooks->value => true,
                    Feature::PrioritySupport->value => true,
                ],
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large organizations.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'limits' => [
                    Limit::CreditsMonthly->value => -1,
                    Limit::Seats->value => -1,
                ],
                'features' => [
                    Feature::CreditPacks->value => true,
                    Feature::AnnualRollover->value => true,
                    Feature::AuditLogs->value => true,
                    Feature::ApiAccess->value => true,
                    Feature::Webhooks->value => true,
                    Feature::PrioritySupport->value => true,
                    Feature::SsoSaml->value => true,
                    Feature::CustomDomain->value => true,
                ],
                'trial_days' => 0,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
