<?php

namespace Database\Factories\Billing;

use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanGrant>
 */
class PlanGrantFactory extends Factory
{
    protected $model = PlanGrant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'plan_id' => fn () => Plan::factory()->soldForLifetime(),
            'purchased_by' => fn (array $attributes) => Workspace::find($attributes['workspace_id'])->owner_id,
            'source' => PlanGrantSource::LifetimePurchase,
            'status' => PlanGrantStatus::Pending,
            'price_cents' => 49000,
            'currency' => 'usd',
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $workspace->id,
            'purchased_by' => $workspace->owner_id,
        ]);
    }

    public function forPlan(Plan $plan): static
    {
        return $this->state(fn (): array => ['plan_id' => $plan->id]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => PlanGrantStatus::Active,
            'granted_at' => now(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => PlanGrantStatus::Revoked,
            'granted_at' => now()->subMonth(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => PlanGrantStatus::Active,
            'granted_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
        ]);
    }
}
