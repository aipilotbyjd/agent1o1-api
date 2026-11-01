<?php

namespace App\Ai\Tools;

use App\Models\Agents\Skill;
use App\Models\Agents\SkillScript;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `SkillDraftAgent` hands back a drafted skill (see `ToolSubmission`).
 */
class SubmitSkillDraftTool implements Tool
{
    public const NAME = 'submit_skill_draft';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits the drafted skill.';
    }

    public function handle(Request $request): Stringable|string
    {
        return 'Received.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('A short, specific skill name, at most 5 words.')->required(),
            'description' => $schema->string()->description('One or two sentences on what the skill does and when an agent should load it.')->required(),
            'category' => $schema->string()->enum(Skill::CATEGORIES)->required(),
            'icon' => $schema->string()->enum(Skill::ICONS)->required(),
            'color' => $schema->string()->enum(Skill::COLORS)->required(),
            'tags' => $schema->array()->items($schema->string())->description('Up to 5 short lowercase tags.')->required(),
            'instructions' => $schema->string()->description('The full playbook the agent follows, in Markdown.')->required(),
            'references' => $schema->array()->items($schema->object([
                'title' => $schema->string()->description('Short title, e.g. "Follow-up email template".')->required(),
                'content' => $schema->string()->description('The template, checklist, rubric or reference text, in Markdown.')->required(),
            ]))->description('Supporting material the instructions point to. Empty when none is needed.')->required(),
            'scripts' => $schema->array()->items($schema->object([
                'name' => $schema->string()->description('A file-like name, e.g. "compute_mrr".')->required(),
                'description' => $schema->string()->description('What the script computes, its inputs and its output.')->required(),
                'language' => $schema->string()->enum(SkillScript::LANGUAGES)->required(),
                'code' => $schema->string()->description('The complete, runnable script.')->required(),
            ]))->description('Helper scripts for deterministic computation. Empty when none is needed.')->required(),
        ];
    }
}
