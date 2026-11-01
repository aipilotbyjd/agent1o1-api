<?php

namespace Database\Factories\SuperAdmin;

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<SuperAdmin>
 */
class SuperAdminFactory extends Factory
{
    protected $model = SuperAdmin::class;

    /**
     * The password every factory-made super admin signs in with.
     */
    public const PASSWORD = 'correct-horse-battery-staple';

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make(self::PASSWORD),
            'role' => SuperAdminRole::Viewer,
            'password_changed_at' => now(),
        ];
    }

    public function role(SuperAdminRole $role): static
    {
        return $this->state(fn (): array => ['role' => $role]);
    }

    public function owner(): static
    {
        return $this->role(SuperAdminRole::Owner);
    }

    public function operator(): static
    {
        return $this->role(SuperAdminRole::Operator);
    }

    public function finance(): static
    {
        return $this->role(SuperAdminRole::Finance);
    }

    public function support(): static
    {
        return $this->role(SuperAdminRole::Support);
    }

    public function viewer(): static
    {
        return $this->role(SuperAdminRole::Viewer);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (): array => [
            'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['disabled_at' => now()]);
    }

    public function locked(): static
    {
        return $this->state(fn (): array => ['locked_until' => now()->addMinutes(15)]);
    }

    public function withExpiredPassword(): static
    {
        return $this->state(fn (): array => ['password_changed_at' => null]);
    }
}
