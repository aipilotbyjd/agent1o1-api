<?php

namespace Database\Factories\Agents;

use App\Models\Agents\Skill;
use App\Models\Agents\SkillScript;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillScript>
 */
class SkillScriptFactory extends Factory
{
    protected $model = SkillScript::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'skill_id' => Skill::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'language' => 'python',
            'code' => "print('hello')",
            'is_enabled' => true,
        ];
    }

    public function forSkill(Skill $skill): static
    {
        return $this->state(fn (): array => ['skill_id' => $skill->id]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }
}
