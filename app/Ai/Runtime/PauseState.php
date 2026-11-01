<?php

namespace App\Ai\Runtime;

use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

/**
 * What a turn waiting on approvals needs to resume exactly where it
 * stopped, kept on its reply as `paused_state`. Shared by every runtime
 * that pauses for approvals (workspace agents and the assistant).
 *
 * The SDK resumes from the conversation history: the latest assistant
 * message must carry the calls of the step that paused, the undecided ones
 * still unanswered, with the provider's replay blocks, which Anthropic and
 * Gemini need verbatim (thinking signatures) to accept the resume. A stored
 * reply flattens all its steps into one list of calls, so the paused step's
 * call ids are recorded and replayed as their own message.
 *
 * Turns paused before the upgrade to Laravel AI 1.x stored the blocks
 * under `provider_content_blocks`; they are still read so those turns can
 * be resumed.
 */
final class PauseState
{
    /**
     * @return array{pending_tool_call_ids: list<string>, step_tool_call_ids: list<string>, provider: string|null, replay_blocks: array<int, mixed>}
     */
    public static function from(AgentResponse $response): array
    {
        $pending = $response->pendingApprovals->map(fn (PendingApproval $approval): string => $approval->id)->values()->all();

        /** @var Step|null $pausedStep */
        $pausedStep = $response->steps->last();

        $stepToolCallIds = collect($pausedStep?->toolCalls ?? [])->map(fn (ToolCall $call): string => $call->id)->values()->all();

        return [
            'pending_tool_call_ids' => $pending,
            'step_tool_call_ids' => array_values(array_unique([...$stepToolCallIds, ...$pending])),
            'provider' => $response->meta->provider,
            'replay_blocks' => $pausedStep?->replayBlocks ?? [],
        ];
    }

    /**
     * The paused reply as the SDK must see it on resume: earlier steps'
     * calls with their results, then the paused step's calls, those still
     * waiting left unanswered.
     *
     * @param  array<int, array<string, mixed>>|null  $toolCalls
     * @param  array<int, array<string, mixed>>|null  $toolResults
     * @param  array<string, mixed>|null  $state
     * @return list<Message>
     */
    public static function replay(?string $content, ?array $toolCalls, ?array $toolResults, ?array $state): array
    {
        $state ??= [];
        $stepIds = $state['step_tool_call_ids'] ?? [];
        $results = collect($toolResults ?? [])->keyBy('id');
        $calls = collect($toolCalls ?? []);

        $earlier = $calls->reject(fn (array $call): bool => in_array($call['id'], $stepIds, true))
            ->filter(fn (array $call): bool => $results->has($call['id']))
            ->values();

        $step = $calls->filter(fn (array $call): bool => in_array($call['id'], $stepIds, true))->values();

        $messages = [];

        if ($earlier->isNotEmpty()) {
            $messages[] = new AssistantMessage('', $earlier->map(ToolCall::fromArray(...)));
            $messages[] = new ToolResultMessage($earlier->map(fn (array $call): ToolResult => ToolResult::fromArray($results[$call['id']])));
        }

        $messages[] = new AssistantMessage(
            (string) $content,
            $step->map(ToolCall::fromArray(...)),
            replayBlocks: self::replayBlocks($state),
            replayBlocksProvider: $state['provider'] ?? null,
        );

        $answered = $step->filter(fn (array $call): bool => $results->has($call['id']));

        if ($answered->isNotEmpty()) {
            $messages[] = new ToolResultMessage($answered->map(fn (array $call): ToolResult => ToolResult::fromArray($results[$call['id']]))->values());
        }

        return $messages;
    }

    /**
     * The paused step's replay blocks, whichever key they were stored under.
     *
     * @param  array<string, mixed>  $state
     * @return array<int, mixed>
     */
    public static function replayBlocks(array $state): array
    {
        return $state['replay_blocks'] ?? $state['provider_content_blocks'] ?? [];
    }
}
