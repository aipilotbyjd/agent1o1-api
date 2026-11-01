<?php

namespace App\Nodes\Integrations\Jira;

/**
 * Jira Cloud keeps rich text (descriptions, comments) as Atlassian Document
 * Format, a tree of typed nodes. A flow works in plain text and Markdown, so
 * this turns Markdown into ADF on the way in and ADF into readable text on
 * the way out.
 *
 * The Markdown understood is the everyday part: headings, bullet and numbered
 * lists, quotes, fenced code, and inline bold, italic, code and links. Nesting
 * lists is not supported (an indented item joins the list it follows).
 */
final class JiraAdf
{
    /**
     * Markdown (or plain text) as an ADF document.
     *
     * @return array{type: string, version: int, content: list<array<string, mixed>>}
     */
    public static function fromMarkdown(string $markdown): array
    {
        $lines = preg_split('/\R/', str_replace("\t", '    ', trim($markdown))) ?: [];
        $blocks = [];
        $paragraph = [];
        $i = 0;

        $flush = function () use (&$blocks, &$paragraph): void {
            if ($paragraph !== []) {
                $blocks[] = ['type' => 'paragraph', 'content' => self::inlineWithBreaks($paragraph)];
                $paragraph = [];
            }
        };

        while ($i < count($lines)) {
            $line = $lines[$i];

            if (preg_match('/^\s*```\s*([A-Za-z0-9_+-]*)\s*$/', $line, $fence) === 1) {
                $flush();
                $code = [];

                for ($i++; $i < count($lines) && preg_match('/^\s*```\s*$/', $lines[$i]) !== 1; $i++) {
                    $code[] = $lines[$i];
                }

                $block = ['type' => 'codeBlock'];
                $block['attrs'] = $fence[1] !== '' ? ['language' => $fence[1]] : (object) [];

                if ($code !== []) {
                    $block['content'] = [['type' => 'text', 'text' => implode("\n", $code)]];
                }

                $blocks[] = $block;
                $i++;

                continue;
            }

            if (trim($line) === '') {
                $flush();
                $i++;

                continue;
            }

            if (preg_match('/^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/', $line, $heading) === 1) {
                $flush();
                $blocks[] = ['type' => 'heading', 'attrs' => ['level' => strlen($heading[1])], 'content' => self::inline($heading[2])];
                $i++;

                continue;
            }

            if (preg_match('/^\s*([-*+])\s+(.*)$/', $line) === 1 || preg_match('/^\s*\d+[.)]\s+(.*)$/', $line) === 1) {
                $flush();
                $ordered = preg_match('/^\s*\d+[.)]\s+/', $line) === 1;
                $items = [];

                for (; $i < count($lines); $i++) {
                    $matched = $ordered
                        ? preg_match('/^\s*\d+[.)]\s+(.*)$/', $lines[$i], $item)
                        : preg_match('/^\s*[-*+]\s+(.*)$/', $lines[$i], $item);

                    if ($matched !== 1) {
                        break;
                    }

                    $items[] = ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => self::inline($item[1])]]];
                }

                $blocks[] = ['type' => $ordered ? 'orderedList' : 'bulletList', 'content' => $items];

                continue;
            }

            if (preg_match('/^\s*>\s?(.*)$/', $line) === 1) {
                $flush();
                $quoted = [];

                for (; $i < count($lines) && preg_match('/^\s*>\s?(.*)$/', $lines[$i], $quote) === 1; $i++) {
                    $quoted[] = $quote[1];
                }

                $blocks[] = ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => self::inlineWithBreaks($quoted)]]];

                continue;
            }

            $paragraph[] = $line;
            $i++;
        }

        $flush();

        return ['type' => 'doc', 'version' => 1, 'content' => $blocks === [] ? [['type' => 'paragraph', 'content' => []]] : $blocks];
    }

    /**
     * An ADF document (or any node of one) as plain text.
     */
    public static function toText(mixed $node): string
    {
        if (is_string($node)) {
            return $node;
        }

        if (! is_array($node)) {
            return '';
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", self::block($node)) ?? '');
    }

    /**
     * @param  list<string>  $lines
     * @return list<array<string, mixed>>
     */
    private static function inlineWithBreaks(array $lines): array
    {
        $content = [];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $content[] = ['type' => 'hardBreak'];
            }

            array_push($content, ...self::inline(trim($line)));
        }

        return $content;
    }

    /**
     * Inline Markdown as text nodes with marks.
     *
     * @return list<array<string, mixed>>
     */
    private static function inline(string $text): array
    {
        $pattern = '/(`[^`\n]+`|\*\*[^*\n]+\*\*|__[^_\n]+__|\[[^\]\n]+\]\([^)\s]+\)|(?<![*\w])\*[^*\s][^*\n]*\*(?![*\w])|(?<![_\w])_[^_\s][^_\n]*_(?![_\w]))/';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $nodes = [];

        foreach ($parts as $part) {
            $node = match (true) {
                preg_match('/^`([^`]+)`$/', $part, $m) === 1 => ['type' => 'text', 'text' => $m[1], 'marks' => [['type' => 'code']]],
                preg_match('/^(?:\*\*|__)(.+)(?:\*\*|__)$/', $part, $m) === 1 => ['type' => 'text', 'text' => $m[1], 'marks' => [['type' => 'strong']]],
                preg_match('/^\[([^\]]+)\]\(([^)\s]+)\)$/', $part, $m) === 1 && self::isSafeLink($m[2]) => ['type' => 'text', 'text' => $m[1], 'marks' => [['type' => 'link', 'attrs' => ['href' => $m[2]]]]],
                preg_match('/^[*_](.+)[*_]$/', $part, $m) === 1 && $part !== '' => ['type' => 'text', 'text' => $m[1], 'marks' => [['type' => 'em']]],
                default => ['type' => 'text', 'text' => $part],
            };

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * Only web and mail links become links: a `javascript:` address is text.
     */
    private static function isSafeLink(string $href): bool
    {
        return preg_match('~^(https?://|mailto:)~i', $href) === 1;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function block(array $node): string
    {
        $type = (string) ($node['type'] ?? '');
        $children = (array) ($node['content'] ?? []);
        $inner = fn (): string => implode('', array_map(fn (mixed $child): string => is_array($child) ? self::block($child) : '', $children));

        return match ($type) {
            'text' => (string) ($node['text'] ?? ''),
            'hardBreak' => "\n",
            'mention' => (string) ($node['attrs']['text'] ?? '@user'),
            'emoji' => (string) ($node['attrs']['text'] ?? $node['attrs']['shortName'] ?? ''),
            'inlineCard', 'blockCard' => (string) ($node['attrs']['url'] ?? ''),
            'media', 'mediaSingle', 'mediaGroup' => $type === 'media' ? '[attachment]' : $inner(),
            'rule' => "\n---\n",
            'paragraph' => $inner()."\n\n",
            'heading' => str_repeat('#', (int) ($node['attrs']['level'] ?? 1)).' '.$inner()."\n\n",
            'blockquote' => '> '.str_replace("\n", "\n> ", trim($inner()))."\n\n",
            'codeBlock' => "```\n".trim($inner())."\n```\n\n",
            'bulletList' => implode("\n", array_map(fn (mixed $item): string => '- '.trim(self::block((array) $item)), $children))."\n\n",
            'orderedList' => implode("\n", array_map(fn (mixed $item, int $index): string => ($index + 1).'. '.trim(self::block((array) $item)), $children, array_keys($children)))."\n\n",
            'listItem', 'taskItem' => trim($inner()),
            'table' => implode("\n", array_map(fn (mixed $row): string => trim(self::block((array) $row)), $children))."\n\n",
            'tableRow' => implode(' | ', array_map(fn (mixed $cell): string => trim(self::block((array) $cell)), $children)),
            default => $inner(),
        };
    }
}
