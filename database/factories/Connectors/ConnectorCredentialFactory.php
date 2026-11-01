<?php

namespace Database\Factories\Connectors;

use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConnectorCredential>
 */
class ConnectorCredentialFactory extends Factory
{
    protected $model = ConnectorCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'connector_id' => fn () => Connector::factory()->create()->id,
            'name' => fake()->words(2, true),
            'data' => ['api_key' => 'sk_test_'.fake()->uuid()],
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function forConnector(Connector $connector): static
    {
        return $this->state(fn (): array => ['connector_id' => $connector->id]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }
}
