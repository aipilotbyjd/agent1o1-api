<?php

namespace App\Ai\Tools\Workspace;

use App\Enums\Agents\AgentMessageRole;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Services\Agents\UntrustedContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reads back the full result of a tool call made earlier in this
 * conversation. Earlier results are replayed cut short
 * (`WorkspaceAgent::MAX_REPLAYED_RESULT_CHARS`) so a long page or export
 * isn't paid for again on every turn; the whole result stays on the
 * `AgentMessage`, and this tool pages through it.
 *
 * It only ever reads, and only this conversation's own messages, so it is
 * never gated: reading a result back can't repeat the call that made it.
 * Attached by `ToolRegistry` once the conversation has a tool result.
 */
class ReadToolResultTool implements Tool
{
    public const NAME = 'read_tool_result';

    public const int DEFAULT_LENGTH = 8000;

    public const int MAX_LENGTH = 20000;

    public function __construct(private readonly AgentSession $session) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Reads the full result of a tool call made earlier in this conversation, when the history shows it cut short. '
            .'Pass the tool_call_id the cut-short note names, and an offset to read further into a long result. '
            .'This only reads the stored result: it never runs the tool again.';
    }

    public function handle(Request $request): Stringable|string
    {
        $toolCallId = (string) $request['tool_call_id'];
        $offset = max(0, (int) ($request['offset'] ?? 0));
        $length = min(self::MAX_LENGTH, max(1, (int) ($request['length'] ?? self::DEFAULT_LENGTH)));

        $stored = $this->storedResult($toolCallId);

        if ($stored === null) {
            return (string) json_encode(['error' => 'No earlier tool result in this conversation has that tool_call_id.']);
        }

        $result = $stored['result'] ?? null;
        $text = is_string($result) ? $result : (string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $total = mb_strlen($text);

        if ($offset >= $total) {
            return (string) json_encode(['error' => "The offset is past the end of the result, which is {$total} characters long."]);
        }

        $end = min($total, $offset + $length);
        $note = $end < $total
            ? "Characters {$offset}-{$end} of {$total}. Call ".self::NAME." again with offset {$end} to read on."
            : "Characters {$offset}-{$end} of {$total}: the end of the result.";

        return $note."\n".UntrustedContent::wrap('tool_result:'.($stored['name'] ?? 'tool'), mb_substr($text, $offset, $end - $offset));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tool_call_id' => $schema->string()->required(),
            'offset' => $schema->integer()->min(0),
            'length' => $schema->integer()->min(1)->max(self::MAX_LENGTH),
        ];
    }

    /**
     * The stored result for `$toolCallId`, searched in this conversation's
     * own replies only.
     *
     * @return array<string, mixed>|null
     */
    private function storedResult(string $toolCallId): ?array
    {
        if ($toolCallId === '') {
            return null;
        }

        $found = null;

        $this->session->messages()
            ->where('role', AgentMessageRole::Assistant)
            ->whereNotNull('tool_results')
            ->latest('id')
            ->chunk(50, function ($messages) use ($toolCallId, &$found): bool {
                foreach ($messages as $message) {
                    /** @var AgentMessage $message */
                    foreach ($message->tool_results ?? [] as $result) {
                        if (($result['id'] ?? null) === $toolCallId) {
                            $found = $result;

                            return false;
                        }
                    }
                }

                return true;
            });

        return $found;
    }
}
