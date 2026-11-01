<?php

namespace App\Services\Assistant\Runtime;

use App\Models\Assistant\AssistantMessage;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Messages\AssistantMessage as SdkAssistantMessage;
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
 * What a turn waiting on approvals needs to resume exactly where it
 * stopped, kept on its reply as `paused_state`.
 *
 * The SDK resumes from the conversation history: the latest assistant
 * message must carry the calls of the step that paused, the undecided ones
 * still unanswered. A stored reply flattens all its steps into one list of
 * calls, so the paused step's call ids are recorded and replayed as their
 * own message, with the provider's raw content blocks, which Anthropic
 * and Gemini need verbatim (thinking signatures) to accept the resume.
 */
final class PausedStep
{
    /**
     * @return array{pending_tool_call_ids: list<string>, step_tool_call_ids: list<string>, provider: string|null, provider_content_blocks: array<int, mixed>}
     */
    public static function stateFrom(AgentResponse $response): array
    {
        $pending = $response->pendingApprovals->map(fn (PendingApproval $approval): string => $approval->id)->values()->all();

        return [
            'pending_tool_call_ids' => $pending,
            'step_tool_call_ids' => array_values(array_unique([...self::stepToolCallIds($response), ...$pending])),
            'provider' => $response->meta->provider,
            'provider_content_blocks' => $response->pausedProviderContentBlocks(),
        ];
    }

    /**
     * The paused reply as the SDK must see it on resume: earlier steps'
     * calls with their results, then the paused step's calls: those still
     * waiting left unanswered.
     *
     * @return list<Message>
     */
    public static function replay(AssistantMessage $message): array
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
            $messages[] = new SdkAssistantMessage('', $earlier->map(ToolCall::fromArray(...)));
            $messages[] = new ToolResultMessage($earlier->map(fn (array $call): ToolResult => ToolResult::fromArray($results[$call['id']])));
        }

        $messages[] = new SdkAssistantMessage(
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
     * The calls made in the step that paused. A streamed reply has no step
     * objects, so they are read off the event stream: the tool calls after
     * the previous step's results and before the approval request.
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
