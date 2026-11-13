<?php

namespace Database\Factories\Sandbox;

use App\Enums\Sandbox\SandboxDriverType;
use App\Models\Sandbox\SandboxProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SandboxProvider>
 */
class SandboxProviderFactory extends Factory
{
    protected $model = SandboxProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'driver' => SandboxDriverType::E2B->value,
            'is_enabled' => true,
            'is_default' => false,
            'priority' => 100,
            'weight' => 1,
            'config' => ['api_url' => 'https://api.e2b.test', 'domain' => 'e2b.test'],
            'credentials' => ['api_key' => 'e2b-key-'.fake()->bothify('????')],
            'price_per_hour_usd' => 0.1656,
        ];
    }

    public function e2b(): static
    {
        return $this->state(fn () => ['driver' => SandboxDriverType::E2B->value]);
    }

    public function piston(string $url = 'https://93.184.216.36'): static
    {
        return $this->state(fn () => [
            'driver' => SandboxDriverType::Piston->value,
            'config' => ['url' => $url],
            'credentials' => null,
        ]);
    }

    public function runner(string $url = 'https://93.184.216.35'): static
    {
        return $this->state(fn () => [
            'driver' => SandboxDriverType::Runner->value,
            'config' => ['url' => $url],
            'credentials' => ['token' => 'runner-token-'.fake()->bothify('????')],
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }

    public function circuitOpen(): static
    {
        return $this->state(fn () => ['consecutive_failures' => 3, 'circuit_open_until' => now()->addMinutes(2)]);
    }
}
