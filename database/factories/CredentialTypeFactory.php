<?php

namespace Database\Factories;

use App\Models\CredentialType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CredentialType>
 */
class CredentialTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'key' => Str::slug($name).'_'.Str::lower(Str::random(4)),
            'name' => ucfirst($name),
            'description' => fake()->optional()->sentence(),
            'auth_type' => 'api_key',
            'icon' => null,
            'fields' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
            ],
            'oauth' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function oauth(): static
    {
        return $this->state(fn () => [
            'auth_type' => 'oauth2',
            'oauth' => [
                'authorize_url' => 'https://provider.test/oauth/authorize',
                'token_url' => 'https://provider.test/oauth/token',
                'scopes' => ['read', 'write'],
            ],
        ]);
    }
}
