<?php

namespace App\Ai\Tools;

use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Loads one attached skill on demand. `SkillInjector` only lists each skill's
 * name and description, so the model decides when a skill applies and pulls
 * in its full instructions and references through this call, which is also
 * what lets the chat show that a skill was actually used.
 */
class UseSkillTool implements Tool
{
    public const NAME = 'use_skill';

    public function __construct(private readonly Agent $agent) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Loads one of your skills: its full instructions and reference material. '
            .'Call it before answering whenever a skill listed under "## Skills" fits the request, then follow what it returns.';
    }

    public function handle(Request $request): Stringable|string
    {
        $name = trim((string) $request['skill']);

        $skill = $this->agent->skills->first(
            fn (Skill $skill): bool => strcasecmp($skill->name, $name) === 0,
        );

        if ($skill === null) {
            return "No skill named \"{$name}\". Your skills are: ".$this->agent->skills->pluck('name')->implode(', ').'.';
        }

        return $skill->toPrompt();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'skill' => $schema->string()
                ->enum($this->agent->skills->pluck('name')->values()->all())
                ->description('The exact name of the skill to load.')
                ->required(),
        ];
    }
}
