<?php

namespace Database\Factories;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => $name,
            'price_monthly' => 1200,
            'price_yearly' => 12000,
            'limits' => [
                Limit::CreditsMonthly->value => 10000,
                Limit::Seats->value => 5,
            ],
            'features' => [
                Feature::CreditPacks->value => true,
            ],
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
