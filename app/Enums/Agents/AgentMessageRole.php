<?php

namespace App\Enums\Agents;

/**
 * Values match `Laravel\Ai\Messages\MessageRole` exactly, so an
 * `AgentMessage` row converts to an SDK `Message` with no translation layer:
 * see `App\Ai\Agents\WorkspaceAgent::messages()`.
 */
enum AgentMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case ToolResult = 'tool_result';
}
