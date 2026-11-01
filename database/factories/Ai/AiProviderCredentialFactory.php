<?php

namespace Database\Factories\Ai;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Ai\AiProviderCredential;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProviderCredential>
 */
class AiProviderCredentialFactory extends Factory
{
    protected $model = AiProviderCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $apiKey = 'sk-test-'.fake()->uuid();

        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'execution_provider' => 'openai',
            'name' => fake()->words(2, true),
            'data' => ['api_key' => $apiKey],
            'key_hint' => AiProviderCredential::hintFor($apiKey),
            'validation_status' => AiProviderCredentialStatus::Valid,
            'last_validated_at' => now(),
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function provider(string $provider): static
    {
        return $this->state(fn (): array => ['execution_provider' => $provider]);
    }

    public function personal(User $owner): static
    {
        return $this->state(fn (): array => ['scope' => ConnectorCredentialScope::Personal, 'created_by' => $owner->id]);
    }

    public function invalid(): static
    {
        return $this->state(fn (): array => ['validation_status' => AiProviderCredentialStatus::Invalid]);
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
