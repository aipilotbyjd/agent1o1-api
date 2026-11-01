<?php

namespace Database\Factories\Billing;

use App\Enums\Billing\OverageInvoiceAttemptStatus;
use App\Models\Billing\OverageInvoiceAttempt;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OverageInvoiceAttempt>
 */
class OverageInvoiceAttemptFactory extends Factory
{
    protected $model = OverageInvoiceAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'status' => OverageInvoiceAttemptStatus::Pending,
            'credits' => 1_000,
            'amount_cents' => 500,
            'allocations' => [],
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OverageInvoiceAttemptStatus::Pending]);
    }
}
