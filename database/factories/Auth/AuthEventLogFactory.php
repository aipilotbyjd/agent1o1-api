<?php

namespace Database\Factories\Auth;

use App\Enums\Auth\AuthEvent;
use App\Models\Auth\AuthEventLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthEventLog>
 */
class AuthEventLogFactory extends Factory
{
    protected $model = AuthEventLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fn (array $attributes) => User::query()->find($attributes['user_id'])?->email ?? fake()->safeEmail(),
            'event' => AuthEvent::LoggedIn,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'context' => null,
        ];
    }

    public function event(AuthEvent $event): static
    {
        return $this->state(fn (array $attributes) => ['event' => $event]);
    }

    /**
     * A failed sign-in against an address with no account behind it.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'email' => fake()->safeEmail(),
            'event' => AuthEvent::LoginFailed,
        ]);
    }
}
