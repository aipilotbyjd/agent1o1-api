<?php

namespace App\Enums\Assistant;

/**
 * `User`/`Assistant`/`ToolResult` match `Laravel\Ai\Messages\MessageRole`.
 * `Recap` is the assistant's own: a summary that replaces older messages
 * in the context sent to the model once a conversation grows long.
 */
enum AssistantMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case ToolResult = 'tool_result';
    case Recap = 'recap';
}
