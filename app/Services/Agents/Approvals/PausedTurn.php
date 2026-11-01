<?php

namespace App\Services\Agents\Approvals;

use App\Models\Agents\AgentMessage;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Laravel\Ai\Streaming\Events\ToolCall as ToolCallEvent;
use Laravel\Ai\Streaming\Events\ToolResult as ToolResultEvent;

/**
 * What a turn waiting on approvals needs to pick up exactly where it
 * stopped, stored on its assistant message as `paused_state`.
 *
 * The SDK resumes a paused turn from the conversation history: the latest
 * assistant message must still carry the calls waiting for a decision,
 * unanswered. `AgentMessage` flattens a turn's steps into one set of calls
 * and results, so the step that paused is recorded separately
 * (`step_tool_call_ids`) and replayed as its own assistant message, with the
 * provider's raw content blocks for that step when there are any, since
 * Anthropic and Gemini need them verbatim (thinking signatures) to accept
 * the continuation.
 */
final class PausedTurn
{
    /**
     * @return array{pending_tool_call_ids: list<string>, step_tool_call_ids: list<string>, provider: string|null, provider_content_blocks: array<int, mixed>, paused_at: string}
     */
    public static function stateFrom(AgentResponse $response): array
    {
        $pending = $response->pendingApprovals->map(fn (PendingApproval $approval): string => $approval->id)->values()->all();

        return [
            'pending_tool_call_ids' => $pending,
            'step_tool_call_ids' => array_values(array_unique([...self::stepToolCallIds($response), ...$pending])),
            'provider' => $response->meta->provider,
            'provider_content_blocks' => $response->pausedProviderContentBlocks(),
            'paused_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The paused turn as the SDK must see it on resume: earlier steps'
     * calls with their results, then the paused step's calls: those still
     * waiting left unanswered.
     *
     * @return list<Message>
     */
    public static function replay(AgentMessage $message): array
    {
        $state = $message->paused_state ?? [];
        $stepIds = $state['step_tool_call_ids'] ?? [];
        $results = collect($message->tool_results ?? [])->keyBy('id');
        $calls = collect($message->tool_calls ?? []);

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
            (string) $message->content,
            $step->map(ToolCall::fromArray(...)),
            $state['provider_content_blocks'] ?? [],
            $state['provider'] ?? null,
        );

        $answered = $step->filter(fn (array $call): bool => $results->has($call['id']));

        if ($answered->isNotEmpty()) {
            $messages[] = new ToolResultMessage($answered->map(fn (array $call): ToolResult => ToolResult::fromArray($results[$call['id']]))->values());
        }

        return $messages;
    }

    /**
     * The calls made in the step that paused. A streamed turn has no step
     * objects, so they are read back off the event stream: the tool calls
     * after the previous step's results and before the approval request.
     *
     * @return list<string>
     */
    private static function stepToolCallIds(AgentResponse $response): array
    {
        if (! $response instanceof StreamedAgentResponse) {
            return collect($response->steps->last()?->toolCalls ?? [])->map(fn (ToolCall $call): string => $call->id)->values()->all();
        }

        $ids = [];
        $seenCall = false;

        foreach ($response->events->reverse() as $event) {
            if ($event instanceof ToolApprovalRequest) {
                continue;
            }

            if ($event instanceof ToolCallEvent) {
                $ids[] = $event->toolCall->id;
                $seenCall = true;

                continue;
            }

            if ($event instanceof ToolResultEvent && $seenCall) {
                break;
            }
        }

        return array_reverse($ids);
    }
}
