<?php

namespace App\Agents\Tools;

use App\Models\AgentSkill;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Loads a single attached skill's full instructions + references on demand —
 * the "progressive disclosure" counterpart to ListSkillsTool's lightweight catalog.
 */
class LoadSkillTool implements Tool
{
    public function __construct(
        private readonly Collection $skills,
    ) {}

    public function description(): Stringable|string
    {
        return 'Load the full instructions and reference material for one of your attached skills '
            .'by slug. Call this before following a skill whose catalog description looks relevant '
            .'to the current request.';
    }

    public function handle(Request $request): Stringable|string
    {
        $slug = $request['skill_slug'] ?? '';

        if (! $slug) {
            return 'Error: skill_slug is required.';
        }

        $skill = $this->skills->firstWhere('slug', $slug);

        if (! $skill instanceof AgentSkill) {
            return "Error: Skill [{$slug}] is not attached to this agent.";
        }

        $parts = ["## Skill: {$skill->name}"];

        if ($skill->description) {
            $parts[] = $skill->description;
        }

        $parts[] = $skill->instructions;

        foreach ($skill->references as $reference) {
            $parts[] = "\n### {$reference->title}\n{$reference->content}";
        }

        return implode("\n", $parts);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'skill_slug' => $schema->string()->required(),
        ];
    }
}
