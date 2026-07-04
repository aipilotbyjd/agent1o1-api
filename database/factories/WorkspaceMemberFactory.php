<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceMember>
 */
class WorkspaceMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement(Role::assignable()),
            'joined_at' => fake()->dateTimeBetween('-90 days', 'now'),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => Role::Owner]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin]);
    }

    public function editor(): static
    {
        return $this->state(fn () => ['role' => Role::Editor]);
    }

    public function viewer(): static
    {
        return $this->state(fn () => ['role' => Role::Viewer]);
    }
}
