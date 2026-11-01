<?php

namespace Database\Factories\Referrals;

use App\Enums\Referrals\ReferralApprovalMode;
use App\Models\Referrals\ReferralProgram;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralProgram>
 */
class ReferralProgramFactory extends Factory
{
    protected $model = ReferralProgram::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'is_active' => true,
            'is_default' => false,
            'default_hold_days' => 0,
            'fraud_checks' => ['card_fingerprint' => false],
        ];
    }

    public function asDefault(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    public function withHold(int $days): static
    {
        return $this->state(fn (): array => ['default_hold_days' => $days]);
    }

    public function manualApproval(): static
    {
        return $this->state(fn (): array => ['approval_mode' => ReferralApprovalMode::Manual]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
