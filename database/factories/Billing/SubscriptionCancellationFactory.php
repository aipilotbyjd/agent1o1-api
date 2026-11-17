<?php

namespace Database\Factories\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\CancellationOffer;
use App\Enums\Billing\CancellationOutcome;
use App\Enums\Billing\CancellationReason;
use App\Models\Billing\SubscriptionCancellation;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionCancellation>
 */
class SubscriptionCancellationFactory extends Factory
{
    protected $model = SubscriptionCancellation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'user_id' => fn (array $attributes) => Workspace::find($attributes['workspace_id'])->owner_id,
            'interval' => BillingInterval::Monthly,
            'reason' => CancellationReason::Other,
            'outcome' => CancellationOutcome::Canceled,
            'months_subscribed' => 0,
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $workspace->id,
            'user_id' => $workspace->owner_id,
        ]);
    }

    public function discountAccepted(int $percent = 5, int $months = 3): static
    {
        return $this->state(fn (): array => [
            'reason' => CancellationReason::TooExpensive,
            'offer' => CancellationOffer::Discount,
            'outcome' => CancellationOutcome::DiscountAccepted,
            'discount_percent' => $percent,
            'discount_months' => $months,
        ]);
    }
}
