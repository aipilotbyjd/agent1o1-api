<?php

namespace App\Ai\Runtime;

use App\Models\Artifacts\Artifact;

/**
 * How much of a model's context window a conversation's history may fill.
 * A message count alone can't keep a request inside the window: fifty short
 * messages are a few thousand tokens, while a handful of pasted documents
 * can be far past it. The budget is the history's share of the window, less
 * what every request carries anyway (instructions, tool definitions, room
 * for the reply).
 *
 * Every figure is an estimate made without a tokenizer, and errs on the
 * high side: text outside English often runs well under four characters a
 * token, and a model missing from the catalog may have a smaller window
 * than the default assumed for it.
 */
final class ContextBudget
{
    /**
     * Assumed when the model's catalog entry doesn't state its window.
     */
    public const int DEFAULT_WINDOW_TOKENS = 128_000;

    /**
     * The share of the window the history may use, leaving headroom for
     * the estimates being off.
     */
    public const float HISTORY_SHARE = 0.6;

    public const int CHARS_PER_TOKEN = 3;

    /**
     * Rough size of one tool's definition (name, description, schema).
     */
    public const int TOKENS_PER_TOOL = 400;

    /**
     * Room kept for the reply when the agent doesn't set `max_tokens`.
     */
    public const int DEFAULT_OUTPUT_TOKENS = 8_192;

    /**
     * The least history budget, however large the instructions are: the
     * latest exchange is always kept anyway (see `WorkspaceAgent`).
     */
    public const int MIN_HISTORY_TOKENS = 4_000;

    public const int IMAGE_TOKENS = 1_600;

    /**
     * Roughly what a PDF or other binary document costs per byte: a page is
     * typically 50-100 KB and a few thousand tokens.
     */
    public const int DOCUMENT_BYTES_PER_TOKEN = 20;

    public static function historyTokens(?int $windowTokens, string $instructions, int $toolCount, ?int $maxOutputTokens): int
    {
        $window = $windowTokens !== null && $windowTokens > 0 ? $windowTokens : self::DEFAULT_WINDOW_TOKENS;

        $budget = (int) floor($window * self::HISTORY_SHARE)
            - self::estimate($instructions)
            - $toolCount * self::TOKENS_PER_TOOL
            - ($maxOutputTokens ?? self::DEFAULT_OUTPUT_TOKENS);

        return max(self::MIN_HISTORY_TOKENS, $budget);
    }

    public static function estimate(string $text): int
    {
        return (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN);
    }

    public static function attachmentTokens(Artifact $artifact): int
    {
        $config = config('artifacts.message_attachments');
        $bytes = max(0, (int) $artifact->size);

        return match (true) {
            in_array($artifact->mime_type, $config['image_mime_types'] ?? [], true) => self::IMAGE_TOKENS,
            in_array($artifact->mime_type, $config['text_mime_types'] ?? [], true) => (int) ceil($bytes / self::CHARS_PER_TOKEN),
            default => (int) ceil($bytes / self::DOCUMENT_BYTES_PER_TOKEN),
        };
    }
}
