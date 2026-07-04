<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use App\Models\AgentSkill;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets an agent rewrite an attached skill's instructions for self-improvement.
 */
class UpdateSkillTool implements Tool
{
    public function __construct(
        private readonly Agent $agent,
    ) {}

    public function description(): Stringable|string
    {
        return 'Update the instructions of one of your attached skills to improve future responses. '
            .'Use this when you learn something new or when the user corrects your behavior. '
            .'List available skills with list_skills_tool if you are unsure of the skill slug.';
    }

    public function handle(Request $request): Stringable|string
    {
        $skillSlug = $request['skill_slug'] ?? '';
        $newInstructions = $request['new_instructions'] ?? '';

        if (! $skillSlug || ! $newInstructions) {
            return 'Error: skill_slug and new_instructions are required.';
        }

        $skill = $this->agent->skills()
            ->where('slug', $skillSlug)
            ->first();

        if (! $skill instanceof AgentSkill) {
            return "Error: Skill [{$skillSlug}] is not attached to this agent.";
        }

        $skill->update([
            'instructions' => $newInstructions,
            'version' => $skill->version + 1,
        ]);

        return json_encode([
            'updated' => true,
            'skill_slug' => $skill->slug,
            'new_version' => $skill->version,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'skill_slug' => $schema->string()->required(),
            'new_instructions' => $schema->string()->required(),
            'reason' => $schema->string(),
        ];
    }
}
