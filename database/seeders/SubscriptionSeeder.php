<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::first();

        if (! $workspace) {
            $this->command->warn('SubscriptionSeeder: no workspace found — skipping.');

            return;
        }

        if ($workspace->subscription()->exists()) {
            $this->command->info('SubscriptionSeeder: subscription already exists — skipping.');

            return;
        }

        $plan = Plan::where('slug', 'free')->first();

        if (! $plan) {
            $this->command->warn('SubscriptionSeeder: free plan not found — run PlanSeeder first.');

            return;
        }

        $workspace->subscription()->create([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_interval' => BillingInterval::Monthly,
            'credits_per_cycle' => $plan->limits['credits_monthly'] ?? 1000,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        $this->command->info("SubscriptionSeeder: created free subscription for workspace '{$workspace->name}'.");
    }
}
