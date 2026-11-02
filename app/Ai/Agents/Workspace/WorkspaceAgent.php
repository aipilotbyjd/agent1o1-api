<?php

namespace App\Ai\Agents\Workspace;

use App\Ai\Agents\Concerns\AppliesGenerationSettings;
use App\Ai\Runtime\PauseState;
use App\Ai\Runtime\TranscriptReplay;
use App\Enums\Agents\AgentMessageRole;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Artifacts\Artifact;
use App\Services\Agents\GenerationSettings;
use Laravel\Ai\Attributes\CacheInstructions;
use Laravel\Ai\Attributes\CacheToolDefinitions;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;

/**
 * Wraps an `AgentSession` as a `Laravel\Ai` agent: the standalone-chat path
 * from docs/AGENTS_PLAN.md's "Agent turn loop" section. `$instructions` is
 * pre-composed by `Services\Agents\SkillInjector` (base instructions +
 * attached Skills + always-included knowledge) and `$tools` by
 * `Services\Agents\ToolRegistry`: both handed in by `AgentRunner`; this
 * class has no opinion on either, only exposes what it's given.
 */
#[MaxSteps(15)]
#[CacheInstructions]
#[CacheToolDefinitions]
#[RepairToolCalls]
class WorkspaceAgent implements Agent, Conversational, HasTools
{
    use AppliesGenerationSettings, Promptable;

    /**
     * How many of the most recent messages are sent as prior context. The
     * full transcript stays in `agent_messages`; only what the provider sees
     * is bounded, so a long conversation can't grow past the context window.
     */
    public const int HISTORY_LIMIT = 50;

    /**
     * How much of each earlier tool result is replayed. Every turn resends
     * the whole history, so a fetched web page or long search result would
     * otherwise be paid for again on every later turn of the conversation.
     */
    public const MAX_REPLAYED_RESULT_CHARS = 4000;

    /**
     * `$beforeMessageId` excludes the just-persisted user turn from
     * `messages()` - `AgentRunner` writes it to `agent_messages` before
     * building this class, and the SDK appends the live `prompt()` argument
     * as the latest user message itself, so including it here would
     * duplicate it in the context sent to the provider.
     *
     * `$resumingMessageId` is set when a paused turn is being resumed with
     * approval decisions: that assistant message is replayed with its
     * unanswered calls intact (`PauseState::replay()`) instead of the usual
     * results-only replay, since those calls are what the decisions answer.
     *
     * @param  array<int, Tool>  $tools
     */
    public function __construct(
        private readonly string $instructions,
        private readonly AgentSession $session,
        private readonly ?string $beforeMessageId = null,
        private readonly array $tools = [],
        private readonly ?GenerationSettings $settings = null,
        private readonly ?string $resumingMessageId = null,
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    /**
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        return $this->tools;
    }

    /**
     * The latest `HISTORY_LIMIT` user/assistant messages, oldest first.
     *
     * A user message with no reply after it belongs to a turn that failed:
     * it is kept in the transcript but left out here, so the model isn't
     * handed a question it never answered. When the limit cuts the
     * transcript, the window is also trimmed to start on a user message,
     * since the cut can land mid-exchange.
     *
     * A past user message that carried attachments is replayed with them,
     * so the model can still refer back to a file sent earlier in the
     * conversation.
     *
     * @return iterable<int, Message>
     */
    public function messages(): iterable
    {
        $history = $this->session->messages()
            ->with('attachments')
            ->whereIn('role', [AgentMessageRole::User, AgentMessageRole::Assistant])
            ->when($this->beforeMessageId !== null, fn ($query) => $query->where('id', '!=', $this->beforeMessageId))
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();

        return TranscriptReplay::answeredWindow($history, self::HISTORY_LIMIT, fn (AgentMessage $message): bool => $message->role === AgentMessageRole::User)
            ->flatMap(fn (AgentMessage $message): array => match (true) {
                $message->id === $this->resumingMessageId && $message->paused_state !== null => PauseState::replay($message->content, $message->tool_calls, $message->tool_results, $message->paused_state),
                $message->role === AgentMessageRole::Assistant => TranscriptReplay::assistantTurn($message->content, $message->tool_calls, $message->tool_results, self::MAX_REPLAYED_RESULT_CHARS),
                $message->attachments->isNotEmpty() => [
                    new UserMessage($message->content, $message->attachments->map(fn (Artifact $artifact) => $artifact->toPromptAttachment())),
                ],
                default => [new Message($message->role->value, $message->content)],
            })
            ->values()
            ->all();
    }
}
