<?php

namespace App\Enums\Agents;

/**
 * How one expectation about an agent's answer is checked.
 *
 * The three string types are exact, free and deterministic: the same input
 * always grades the same way. The two tool types check which tools the answer
 * called (`value` is a tool name), which matters most with smaller models:
 * the usual failure is not a wrong answer but a tool never called at all. `LlmRubric` costs a second model call and can
 * disagree with itself between runs, so it is for expectations that genuinely
 * can't be written as a string check ("politely declines", "doesn't invent a
 * refund policy"), not as a default.
 */
enum EvalAssertionType: string
{
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case Equals = 'equals';
    case ToolCalled = 'tool_called';
    case ToolNotCalled = 'tool_not_called';
    case LlmRubric = 'llm_rubric';

    /**
     * Whether grading this assertion requires calling a model.
     */
    public function needsJudge(): bool
    {
        return $this === self::LlmRubric;
    }
}
