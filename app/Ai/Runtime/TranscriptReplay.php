<?php

namespace App\Ai\Runtime;

use Closure;
use Illuminate\Support\Collection;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

/**
 * How a stored conversation is shown to the model again, shared by every
 * runtime that keeps its own transcript (workspace agents and the
 * assistant): which messages make the window, and how an earlier reply's
 * tool calls and results are replayed so a later turn can see what a tool
 * already fetched.
 */
final class TranscriptReplay
{
    /**
     * The transcript (oldest first, at most `$limit` messages) without the
     * user messages nobody answered: those belonged to a turn that failed
     * or was cancelled, and the model shouldn't be handed a question it
     * never answered. When the limit cut the transcript, the window also
     * starts on a user message, never on a reply to one that is gone.
     *
     * @template TMessage
     *
     * @param  Collection<int, TMessage>  $history
     * @param  Closure(TMessage): bool  $isUser
     * @return Collection<int, TMessage>
     */
    public static function answeredWindow(Collection $history, int $limit, Closure $isUser): Collection
    {
        $history = $history->values();

        return $history
            ->filter(fn (mixed $message, int $index): bool => ! $isUser($message)
                || ($history->get($index + 1) !== null && ! $isUser($history->get($index + 1))))
            ->when($history->count() === $limit, fn (Collection $messages) => $messages->skipUntil($isUser))
            ->values();
    }

    /**
     * An earlier reply as the model sees it again: its answered tool calls
     * and their results, then its text. Calls without a stored result are
     * dropped (providers reject a call left unanswered) and results longer
     * than `$maxResultChars` are cut short. `$readToolName` is a tool the
     * model can call to read a cut-short result in full; without one, the
     * note only lets it look a result up again, never repeat an action.
     *
     * @param  array<int, array<string, mixed>>|null  $toolCalls
     * @param  array<int, array<string, mixed>>|null  $toolResults
     * @return list<Message>
     */
    public static function assistantTurn(?string $content, ?array $toolCalls, ?array $toolResults, int $maxResultChars, ?string $readToolName = null): array
    {
        $results = collect($toolResults ?? [])->keyBy('id');
        $calls = collect($toolCalls ?? [])->filter(fn (array $call): bool => $results->has($call['id']))->values();

        $messages = [];

        if ($calls->isNotEmpty()) {
            $messages[] = new AssistantMessage('', $calls->map(ToolCall::fromArray(...)));
            $messages[] = new ToolResultMessage($calls->map(fn (array $call): ToolResult => self::replayedResult($results[$call['id']], $maxResultChars, $readToolName)));
        }

        if ($calls->isEmpty() || filled($content)) {
            $messages[] = new AssistantMessage((string) $content);
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private static function replayedResult(array $stored, int $maxChars, ?string $readToolName): ToolResult
    {
        $result = $stored['result'] ?? null;
        $text = is_string($result) ? $result : (string) json_encode($result);

        if (mb_strlen($text) > $maxChars) {
            $stored['result'] = mb_substr($text, 0, $maxChars).'… '.self::cutShortNote($stored, mb_strlen($text), $readToolName);
        }

        return ToolResult::fromArray($stored);
    }

    /**
     * What the model is told about a result it only sees the start of. It
     * must never be nudged into calling the tool again: for a tool that
     * changes something (sends an email, starts a workflow) that would do
     * it twice.
     *
     * @param  array<string, mixed>  $stored
     */
    private static function cutShortNote(array $stored, int $length, ?string $readToolName): string
    {
        if ($readToolName !== null && isset($stored['id'])) {
            return "[cut short in the conversation history: the full result is {$length} characters. "
                ."Call {$readToolName} with tool_call_id \"{$stored['id']}\" to read the rest]";
        }

        return '[cut short in the conversation history. If you need the rest and this tool only looks things up, '
            .'you may call it again; never repeat a tool call that changes something]';
    }
}
