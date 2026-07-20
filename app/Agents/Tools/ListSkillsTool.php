<?php

namespace App\Agents\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lists the skills attached to the running agent (slug, name, description only) —
 * the catalog an agent uses to decide which skill to load in full via LoadSkillTool.
 */
class ListSkillsTool implements Tool
{
    public function __construct(
        private readonly Collection $skills,
    ) {}

    public function description(): Stringable|string
    {
        return 'List the skills currently attached to you (slug, name, description) so you know '
            .'what is available and can decide what to load with load_skill_tool.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode(
            $this->skills->map(fn ($skill) => [
                'slug' => $skill->slug,
                'name' => $skill->name,
                'description' => $skill->description,
            ])->values()->all()
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
