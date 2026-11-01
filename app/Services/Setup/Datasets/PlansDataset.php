<?php

namespace App\Services\Setup\Datasets;

use App\Enums\Setup\Ownership;
use App\Models\Billing\Plan;
use App\Services\Setup\AbstractSetupDataset;

/**
 * The starting plan catalog; after that the Super Admin pricing manager
 * owns it, so this only ever creates missing plans. Paid plans read their Stripe price IDs from env: they
 * must be created ahead of time in the Stripe dashboard: three recurring
 * prices (monthly, every-3-months, yearly) plus a one-time price for the
 * lifetime purchase. A price ID left unset simply means the plan isn't sold
 * on that interval, which is how the Free plan is sold on none.
 */
class PlansDataset extends AbstractSetupDataset
{
    public function key(): string
    {
        return 'plans';
    }

    public function label(): string
    {
        return 'Plans';
    }

    public function ownership(): Ownership
    {
        return Ownership::AdminOwned;
    }

    public function rows(): int
    {
        return Plan::query()->count();
    }

    protected function dataFiles(): array
    {
        return [database_path('setup/plans.php')];
    }

    public function apply(): void
    {
        $sort = 0;

        foreach ($this->catalog() as $plan) {
            // Create-if-missing only: once a plan exists the Super Admin
            // pricing manager owns it, and re-seeding must not undo its edits.
            Plan::query()->firstOrCreate(
                ['slug' => $plan['slug']],
                [...$plan, 'is_active' => true, 'sort_order' => $sort],
            );

            $sort++;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return require database_path('setup/plans.php');
    }

    public function problems(): array
    {
        $plans = require database_path('setup/plans.php');
        $problems = [];

        foreach ($this->duplicates(array_column($plans, 'slug')) as $slug) {
            $problems[] = "Plan slug [{$slug}] appears more than once.";
        }

        foreach ($plans as $plan) {
            foreach ($this->missing($plan, ['name', 'slug', 'credits_monthly', 'limits']) as $field) {
                $problems[] = 'Plan ['.($plan['slug'] ?? '?')."] has no {$field}.";
            }
        }

        $default = (string) config('billing.default_plan');

        if (! in_array($default, array_column($plans, 'slug'), true)) {
            $problems[] = "The default plan [{$default}] (billing.default_plan) is not in the plan list.";
        }

        return $problems;
    }
}
