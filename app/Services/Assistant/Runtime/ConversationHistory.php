<?php

namespace App\Services\Assistant\Runtime;

use App\Enums\Assistant\AssistantMessageRole;
use App\Models\Assistant\AssistantMessage;
use App\Models\Assistant\AssistantSession;
use Laravel\Ai\Messages\AssistantMessage as SdkAssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

/**
 * The transcript as the model is shown it: the latest user/assistant
 * messages, oldest first, with each earlier reply's tool calls and results
 * replayed so a later turn can see what a tool already fetched.
 */
final class ConversationHistory
{
    public function __construct(
        private readonly AssistantSession $session,
        private readonly ?string $excludeMessageId = null,
        private readonly ?string $resumingMessageId = null,
    ) {}

    /**
     * A user message with no reply after it belonged to a turn that failed
     * or was cancelled: kept in the transcript, left out here so the model
     * isn't handed a question it never answered. When the limit cuts the
     * transcript, the window also starts on a user message.
     *
     * @return list<Message>
     */
    public function messages(): array
    {
        $limit = (int) config('assistant.runtime.history_limit');

        $history = $this->session->messages()
            ->whereIn('role', [AssistantMessageRole::User, AssistantMessageRole::Assistant])
            ->whereNull('compacted_into_id')
            ->when($this->excludeMessageId !== null, fn ($query) => $query->whereKeyNot($this->excludeMessageId))
            ->latest()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $answered = $history->filter(fn (AssistantMessage $message, int $index): bool => $message->role !== AssistantMessageRole::User
            || $history->get($index + 1)?->role === AssistantMessageRole::Assistant);

        return $answered
            ->when($history->count() === $limit, fn ($messages) => $messages->skipUntil(fn (AssistantMessage $message): bool => $message->role === AssistantMessageRole::User))
            ->flatMap(fn (AssistantMessage $message): array => match (true) {
                $message->id === $this->resumingMessageId && $message->paused_state !== null => PausedStep::replay($message),
                $message->role === AssistantMessageRole::Assistant => $this->assistantReply($message),
                default => [new Message('user', (string) $message->content)],
            })
            ->values()
            ->all();
    }

    /**
     * Calls without a stored result are dropped (providers reject a call
     * left unanswered) and long results are cut short.
     *
     * @return list<Message>
     */
    private function assistantReply(AssistantMessage $message): array
    {
        $results = collect($message->tool_results ?? [])->keyBy('id');
        $calls = collect($message->tool_calls ?? [])->filter(fn (array $call): bool => $results->has($call['id']))->values();

        $messages = [];

        if ($calls->isNotEmpty()) {
            $messages[] = new SdkAssistantMessage('', $calls->map(ToolCall::fromArray(...)));
            $messages[] = new ToolResultMessage($calls->map(fn (array $call): ToolResult => $this->replayedResult($results[$call['id']])));
        }

        if ($calls->isEmpty() || filled($message->content)) {
            $messages[] = new SdkAssistantMessage((string) $message->content);
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function replayedResult(array $stored): ToolResult
    {
        $limit = (int) config('assistant.runtime.replayed_result_chars');
        $result = $stored['result'] ?? null;
        $text = is_string($result) ? $result : (string) json_encode($result);

        if (mb_strlen($text) > $limit) {
            $stored['result'] = mb_substr($text, 0, $limit).'… [cut short in the conversation history; call the tool again if you need the rest]';
        }

        return ToolResult::fromArray($stored);
    }
}
