<?php

namespace App\Ai;

use App\Exceptions\ModelSubmissionException;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\Responses\TextResponse;

/**
 * Reads the result an agent using `SubmitsStructuredOutput` handed back:
 * the provider's structured output, or, for an agent configured to use the
 * forced submit tool instead (`ai.structured_output.forced_tool`), that
 * tool call's arguments. Callers get the same array either way.
 */
final class ToolSubmission
{
    /**
     * @return array<string, mixed>
     */
    public static function arguments(TextResponse $response, string $toolName): array
    {
        if ($response instanceof StructuredAgentResponse) {
            return $response->structured !== [] ? $response->structured : throw ModelSubmissionException::missingStructuredOutput();
        }

        $call = $response->toolCalls->first(fn (ToolCall $call): bool => $call->name === $toolName);

        if ($call === null) {
            throw ModelSubmissionException::missingToolCall($toolName);
        }

        return $call->arguments;
    }
}
