<?php

namespace Database\Factories\Billing;

use App\Enums\Billing\CreditGrantSource;
use App\Models\Billing\CreditGrant;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Writes grant rows directly, bypassing `CreditWallet`, so it does not keep
 * `workspaces.topup_credits` in step. Use the wallet in tests that read the
 * balance; use this for tests about the rows themselves.
 *
 * @extends Factory<CreditGrant>
 */
class CreditGrantFactory extends Factory
{
    protected $model = CreditGrant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'source' => CreditGrantSource::Topup,
            'amount' => 1000,
            'remaining' => fn (array $attributes) => $attributes['amount'],
            'priority' => fn (array $attributes) => CreditGrantSource::from(
                $attributes['source'] instanceof CreditGrantSource ? $attributes['source']->value : $attributes['source']
            )->defaultPriority(),
            'starts_at' => now()->subMinute(),
            'expires_at' => null,
            'idempotency_key' => fn () => 'factory:'.Str::uuid(),
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function source(CreditGrantSource $source): static
    {
        return $this->state(fn (): array => ['source' => $source, 'priority' => $source->defaultPriority()]);
    }

    public function expiringAt(\DateTimeInterface $expiresAt): static
    {
        return $this->state(fn (): array => ['expires_at' => $expiresAt]);
    }
}
