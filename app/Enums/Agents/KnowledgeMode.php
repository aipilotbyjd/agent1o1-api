<?php

namespace App\Enums\Agents;

/**
 * How an agent uses a knowledge-base collection attached to it: looked up
 * through `SearchKnowledgeTool` when relevant, or put in its system prompt
 * on every turn by `SkillInjector`.
 */
enum KnowledgeMode: string
{
    case Search = 'search';
    case Always = 'always';
}
