<?php

namespace App\Ai\Agents;

use App\Ai\Tools\SubmitSkillDraftTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\ToolChoice;

/**
 * Turns a plain-language request ("how we triage support tickets") into a
 * starting skill (instructions, references and scripts) answering only
 * through `SubmitSkillDraftTool`.
 */
#[ToolChoice(ToolChoice::tool, SubmitSkillDraftTool::NAME)]
#[MaxSteps(1)]
class SkillDraftAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You write skills for AI agents. A skill is a reusable playbook an agent loads on demand when a task
        calls for it. Given a user's description of a process, draft the skill.

        The description is all an agent sees of the skill before loading it, so say plainly what the skill
        does and the requests it applies to.

        The instructions are what the agent reads once it loads the skill. Write them in Markdown, addressed
        to the agent, covering: when the skill applies, the steps to follow in order, the rules and decisions
        along the way, what to ask the user when information is missing, and the exact format of the result.
        Be specific to the user's process; don't pad with generic advice. Don't claim access to tools or data
        the user didn't mention.

        Put templates, checklists, rubrics and examples the instructions rely on into references, and refer to
        them by title from the instructions. Leave references empty when nothing is worth separating out.

        Add a script only when the task needs a deterministic computation (parsing, totals, date math) that is
        error-prone to do by hand. Scripts are stored with the skill but not run automatically, so the
        instructions must still work without them. Leave scripts empty otherwise.

        Pick the category, icon and color that best fit the skill.

        Submit the draft by calling the submit_skill_draft tool.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [new SubmitSkillDraftTool];
    }
}
