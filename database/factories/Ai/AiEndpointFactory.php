<?php

namespace Database\Factories\Ai;

use App\Models\Ai\AiEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiEndpoint>
 */
class AiEndpointFactory extends Factory
{
    protected $model = AiEndpoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'own-'.Str::lower(Str::random(8)),
            'name' => fake()->words(2, true),
            'base_url' => 'https://93.184.216.34/v1',
            'api_key' => 'sk-'.Str::random(24),
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }

    public function withoutKey(): static
    {
        return $this->state(fn (): array => ['api_key' => null]);
    }
}
