<?php

namespace Database\Factories\Agents;

use App\Models\Agents\Skill;
use App\Models\Agents\SkillReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillReference>
 */
class SkillReferenceFactory extends Factory
{
    protected $model = SkillReference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'skill_id' => Skill::factory(),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(3, true),
            'sort_order' => 0,
        ];
    }

    public function forSkill(Skill $skill): static
    {
        return $this->state(fn (): array => ['skill_id' => $skill->id]);
    }
}
