<?php

namespace Database\Factories\Billing;

use App\Models\Billing\CreditPackDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditPackDefinition>
 */
class CreditPackDefinitionFactory extends Factory
{
    protected $model = CreditPackDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'label' => '2,000 credits',
            'credits' => 2000,
            'price_cents' => 1900,
            'badge' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
