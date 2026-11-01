<?php

namespace Database\Factories\Workspaces;

use App\Enums\Workspaces\AuditAction;
use App\Models\User;
use App\Models\Workspaces\AuditLog;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'actor_id' => null,
            'actor_label' => fake()->safeEmail(),
            'action' => AuditAction::SecretCreated,
            'metadata' => null,
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function by(User $actor): static
    {
        return $this->state(fn (): array => ['actor_id' => $actor->id, 'actor_label' => $actor->email]);
    }
}
