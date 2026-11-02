<?php

namespace App\Services\Assistant\Runtime;

use App\Ai\Runtime\PauseState;
use App\Ai\Runtime\TranscriptReplay;
use App\Enums\Assistant\AssistantMessageRole;
use App\Models\Assistant\AssistantMessage;
use App\Models\Assistant\AssistantSession;
use Laravel\Ai\Messages\Message;

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

        return TranscriptReplay::answeredWindow($history, $limit, fn (AssistantMessage $message): bool => $message->role === AssistantMessageRole::User)
            ->flatMap(fn (AssistantMessage $message): array => match (true) {
                $message->id === $this->resumingMessageId && $message->paused_state !== null => PauseState::replay($message->content, $message->tool_calls, $message->tool_results, $message->paused_state),
                $message->role === AssistantMessageRole::Assistant => TranscriptReplay::assistantTurn($message->content, $message->tool_calls, $message->tool_results, (int) config('assistant.runtime.replayed_result_chars')),
                default => [new Message('user', (string) $message->content)],
            })
            ->values()
            ->all();
    }
}
