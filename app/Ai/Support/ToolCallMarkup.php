<?php

namespace App\Ai\Support;

/**
 * Removes tool-call markup that some models (GLM among them) write into their
 * reply text instead of a structured tool call:
 * `<tool_call>Name<arg_key>k</arg_key><arg_value>v</arg_value></tool_call>`.
 *
 * Used both on a finished reply (`strip()`) and on a live stream, where a block
 * can be split across chunks (`push()` / `finish()`): text inside an open block
 * is held back and dropped, and a chunk that ends partway through the opening
 * tag is held until the next chunk shows whether it is one.
 *
 * A `<tool_call>` that is never closed is only dropped when what follows it
 * looks like a call (`<arg_key>` / `<arg_value>`): a reply that merely
 * mentions the tag keeps the rest of its text, released when the stream ends.
 */
class ToolCallMarkup
{
    private const string OPEN = '<tool_call>';

    private const string CLOSE = '</tool_call>';

    /**
     * @var list<string>
     */
    private const array CALL_MARKERS = ['<arg_key>', '<arg_value>'];

    private bool $inside = false;

    private string $pending = '';

    /**
     * Everything read since the open block's `<tool_call>`, kept in case the
     * block is never closed and turns out not to be a call.
     */
    private string $held = '';

    public static function strip(string $text): string
    {
        $filter = new self;

        return $filter->push($text).$filter->finish();
    }

    /**
     * The part of `$chunk` that is safe to show now.
     */
    public function push(string $chunk): string
    {
        $text = $this->pending.$chunk;
        $this->pending = '';
        $visible = '';

        while ($text !== '') {
            if ($this->inside) {
                $end = strpos($text, self::CLOSE);

                if ($end === false) {
                    $this->pending = $this->partialSuffix($text, self::CLOSE);
                    $this->held .= substr($text, 0, strlen($text) - strlen($this->pending));

                    return $visible;
                }

                $text = ltrim(substr($text, $end + strlen(self::CLOSE)), "\r\n");
                $this->inside = false;
                $this->held = '';

                continue;
            }

            $start = strpos($text, self::OPEN);

            if ($start === false) {
                $held = $this->partialSuffix($text, self::OPEN);
                $this->pending = $held;

                return $visible.substr($text, 0, strlen($text) - strlen($held));
            }

            $visible .= substr($text, 0, $start);
            $text = substr($text, $start + strlen(self::OPEN));
            $this->inside = true;
        }

        return $visible;
    }

    /**
     * What is left once the stream ends. An unclosed block is dropped when it
     * looks like a call, and given back as text when it was only a mention.
     */
    public function finish(): string
    {
        $rest = match (true) {
            ! $this->inside => $this->pending,
            $this->looksLikeCall($this->held.$this->pending) => '',
            default => self::OPEN.$this->held.$this->pending,
        };

        $this->pending = '';
        $this->held = '';
        $this->inside = false;

        return $rest;
    }

    private function looksLikeCall(string $text): bool
    {
        foreach (self::CALL_MARKERS as $marker) {
            if (str_contains($text, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The longest end of `$text` that is the start of `$tag`.
     */
    private function partialSuffix(string $text, string $tag): string
    {
        for ($length = min(strlen($tag) - 1, strlen($text)); $length > 0; $length--) {
            if (str_starts_with($tag, substr($text, -$length))) {
                return substr($text, -$length);
            }
        }

        return '';
    }
}
