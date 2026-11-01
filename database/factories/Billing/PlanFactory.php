<?php

namespace Database\Factories\Billing;

use App\Models\Billing\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price_monthly' => 2900,
            'price_quarterly' => 7900,
            'price_yearly' => 29000,
            'price_lifetime' => 49000,
            'credits_monthly' => 5000,
            'limits' => [],
            'features' => [],
            'stripe_product_id' => null,
            'stripe_price_id_monthly' => 'price_'.fake()->uuid(),
            'stripe_price_id_quarterly' => 'price_'.fake()->uuid(),
            'stripe_price_id_yearly' => 'price_'.fake()->uuid(),
            'stripe_price_id_lifetime' => null,
            'enabled_intervals' => ['monthly', 'quarterly', 'yearly', 'lifetime'],
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Sold as a one-time lifetime purchase. Off by default so a plain
     * `Plan::factory()` can't accidentally satisfy a lifetime checkout.
     */
    public function soldForLifetime(): static
    {
        return $this->state(fn (): array => [
            'stripe_price_id_lifetime' => 'price_'.fake()->uuid(),
        ]);
    }

    /**
     * Sold on the recurring intervals only: no lifetime price.
     */
    public function withoutLifetime(): static
    {
        return $this->state(fn (): array => [
            'stripe_price_id_lifetime' => null,
        ]);
    }

    /**
     * Priced on the interval but withdrawn from sale: the state that
     * separates "switched off" from "never configured".
     *
     * @param  list<string>  $intervals
     */
    public function sellingOnly(array $intervals): static
    {
        return $this->state(fn (): array => ['enabled_intervals' => $intervals]);
    }
}
