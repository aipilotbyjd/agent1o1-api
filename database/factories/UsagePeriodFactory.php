<?php

namespace Database\Factories;

use App\Models\UsagePeriod;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsagePeriod>
 */
class UsagePeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'period_start' => today(),
            'period_end' => today()->addMonthNoOverflow(),
            'credits_limit' => 1000,
            'credits_from_packs' => 0,
            'credits_rolled_over' => 0,
            'credits_used' => 0,
            'executions_total' => 0,
            'is_current' => true,
        ];
    }
}
