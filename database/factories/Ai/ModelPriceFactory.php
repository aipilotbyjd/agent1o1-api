<?php

namespace Database\Factories\Ai;

use App\Enums\Ai\ModelPriceSource;
use App\Models\Ai\ModelPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelPrice>
 */
class ModelPriceFactory extends Factory
{
    protected $model = ModelPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'openai',
            'model_id' => 'gpt-'.fake()->unique()->numberBetween(1, 99999),
            'input_per_mtok' => 2.5,
            'output_per_mtok' => 10,
            'source' => ModelPriceSource::ProviderList,
            'checked_at' => now(),
        ];
    }

    public function priced(float $input, float $output): static
    {
        return $this->state(fn (): array => ['input_per_mtok' => $input, 'output_per_mtok' => $output]);
    }

    public function stale(int $days = 200): static
    {
        return $this->state(fn (): array => ['checked_at' => now()->subDays($days)]);
    }
}
