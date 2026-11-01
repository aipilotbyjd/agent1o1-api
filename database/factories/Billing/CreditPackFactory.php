<?php

namespace Database\Factories\Billing;

use App\Enums\Billing\CreditPackStatus;
use App\Models\Billing\CreditPack;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditPack>
 */
class CreditPackFactory extends Factory
{
    protected $model = CreditPack::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'purchased_by' => fn (array $attributes) => Workspace::find($attributes['workspace_id'])->owner_id,
            'pack_key' => 'small',
            'credits_amount' => 1000,
            'price_cents' => 900,
            'currency' => 'usd',
            'status' => CreditPackStatus::Pending,
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $workspace->id,
            'purchased_by' => $workspace->owner_id,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => CreditPackStatus::Active,
            'purchased_at' => now(),
        ]);
    }
}
