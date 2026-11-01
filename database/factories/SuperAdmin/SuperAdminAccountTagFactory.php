<?php

namespace Database\Factories\SuperAdmin;

use App\Models\SuperAdmin\SuperAdminAccountTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuperAdminAccountTag>
 */
class SuperAdminAccountTagFactory extends Factory
{
    protected $model = SuperAdminAccountTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => 'user',
            'subject_id' => User::factory(),
            'tag' => fake()->unique()->lexify('tag-????'),
        ];
    }
}
