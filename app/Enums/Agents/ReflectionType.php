<?php

namespace App\Enums\Agents;

enum ReflectionType: string
{
    case NewSkill = 'new_skill';
    case SkillFix = 'skill_fix';
    case InstructionUpdate = 'instruction_update';
    case ToolAccess = 'tool_access';

    /**
     * `tool_access` proposals need a human to grant a connector/credential:
     * there is nothing safe to automate, so these never auto-apply
     * regardless of the agent's `apply_behavior`.
     */
    public function isAutoApplyEligible(): bool
    {
        return $this !== self::ToolAccess;
    }
}
