<?php

namespace Database\Factories\Billing;

use App\Models\Billing\StripeWebhookFailure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StripeWebhookFailure>
 */
class StripeWebhookFailureFactory extends Factory
{
    protected $model = StripeWebhookFailure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stripe_event_id' => 'evt_'.Str::random(24),
            'type' => 'checkout.session.completed',
            'error' => 'database unavailable',
            'attempts' => 1,
            'first_failed_at' => now()->subHour(),
            'last_failed_at' => now()->subHour(),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => ['resolved_at' => now()]);
    }
}
