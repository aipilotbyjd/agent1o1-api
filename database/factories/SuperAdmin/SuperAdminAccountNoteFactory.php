<?php

namespace Database\Factories\SuperAdmin;

use App\Models\SuperAdmin\SuperAdminAccountNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuperAdminAccountNote>
 */
class SuperAdminAccountNoteFactory extends Factory
{
    protected $model = SuperAdminAccountNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => 'user',
            'subject_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
