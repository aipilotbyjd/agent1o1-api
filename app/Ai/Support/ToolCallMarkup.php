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
 */
class ToolCallMarkup
{
    private const string OPEN = '<tool_call>';

    private const string CLOSE = '</tool_call>';

    private bool $inside = false;

    private string $pending = '';

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

                    return $visible;
                }

                $text = ltrim(substr($text, $end + strlen(self::CLOSE)), "\r\n");
                $this->inside = false;

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
     * What is left once the stream ends. An unclosed block is dropped.
     */
    public function finish(): string
    {
        $rest = $this->inside ? '' : $this->pending;
        $this->pending = '';
        $this->inside = false;

        return $rest;
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
