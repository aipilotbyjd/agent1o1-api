<?php

namespace Database\Factories\Connectors;

use App\Enums\Connectors\ConnectorAuthType;
use App\Enums\Connectors\ConnectorCategory;
use App\Models\Connectors\Connector;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Connector>
 */
class ConnectorFactory extends Factory
{
    protected $model = Connector::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'key' => Str::slug($name, '_'),
            'name' => $name,
            'description' => fake()->sentence(),
            'icon' => 'plug',
            'color' => '#6366f1',
            'category' => ConnectorCategory::Other,
            'is_featured' => false,
            'auth_type' => ConnectorAuthType::ApiKey,
            'fields' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'string', 'secret' => true, 'required' => true],
            ],
            'oauth' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function oauth(): static
    {
        return $this->state(fn (): array => [
            'auth_type' => ConnectorAuthType::OAuth2,
            'fields' => [],
            'oauth' => [
                'authorize_url' => 'https://example.test/oauth/authorize',
                'token_url' => 'https://example.test/oauth/token',
                'scopes' => ['read'],
            ],
        ]);
    }
}
