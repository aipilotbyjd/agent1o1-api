<?php

namespace App\Services\Web;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Reads an HTML page into what a workflow can use: its title, readable text,
 * Markdown, links and headings, or the parts a selector picks out.
 *
 * Nothing in the page is executed or fetched: the markup is parsed with
 * network access and entity loading off, and scripts, styles and embedded
 * frames are dropped before anything is read.
 */
final class HtmlDocument
{
    /**
     * The elements that never hold readable content.
     */
    private const array DROPPED = ['script', 'style', 'noscript', 'iframe', 'object', 'embed', 'svg', 'canvas', 'template', 'head'];

    /**
     * The page furniture left out when only the main content is wanted.
     */
    private const array FURNITURE = ['nav', 'header', 'footer', 'aside', 'form'];

    private DOMDocument $dom;

    private function __construct(DOMDocument $dom, private readonly ?string $baseUrl)
    {
        $this->dom = $dom;
    }

    public static function parse(string $html, ?string $baseUrl = null): self
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // The XML declaration makes libxml read the markup as UTF-8.
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new self($dom, $baseUrl);
    }

    public function title(): string
    {
        $title = $this->dom->getElementsByTagName('title')->item(0);

        if ($title !== null && trim($title->textContent) !== '') {
            return $this->squash($title->textContent);
        }

        $heading = $this->dom->getElementsByTagName('h1')->item(0);

        return $heading !== null ? $this->squash($heading->textContent) : '';
    }

    /**
     * @return array<string, string>
     */
    public function meta(): array
    {
        $meta = [];

        foreach ($this->dom->getElementsByTagName('meta') as $tag) {
            $name = strtolower($tag->getAttribute('name') ?: $tag->getAttribute('property'));

            if (in_array($name, ['description', 'og:description', 'og:title', 'og:image', 'og:site_name', 'author', 'article:published_time'], true) && $tag->getAttribute('content') !== '') {
                $meta[$name] = $this->squash($tag->getAttribute('content'));
            }
        }

        return $meta;
    }

    public function markdown(bool $mainContentOnly = true): string
    {
        $root = $this->content($mainContentOnly);

        return $root === null ? '' : trim((string) preg_replace("/\n{3,}/", "\n\n", $this->block($root, $mainContentOnly)));
    }

    public function text(bool $mainContentOnly = true): string
    {
        $root = $this->content($mainContentOnly);

        return $root === null ? '' : trim((string) preg_replace(["/[ \t]+/", "/ ?\n ?/", "/\n{3,}/"], [' ', "\n", "\n\n"], $this->plain($root, $mainContentOnly)));
    }

    /**
     * @return list<array{text: string, url: string}>
     */
    public function links(bool $mainContentOnly = false): array
    {
        $root = $this->content($mainContentOnly);
        $links = [];

        if ($root === null) {
            return [];
        }

        foreach ((new DOMXPath($this->dom))->query('.//a[@href]', $root) ?: [] as $anchor) {
            $url = $this->absolute($anchor->getAttribute('href'));

            if ($url !== null && ! isset($links[$url])) {
                $links[$url] = ['text' => $this->squash($anchor->textContent), 'url' => $url];
            }
        }

        return array_values($links);
    }

    /**
     * @return list<array{level: int, text: string}>
     */
    public function headings(): array
    {
        $headings = [];

        foreach ((new DOMXPath($this->dom))->query('//h1|//h2|//h3|//h4|//h5|//h6') ?: [] as $heading) {
            if ($this->squash($heading->textContent) !== '') {
                $headings[] = ['level' => (int) substr($heading->nodeName, 1), 'text' => $this->squash($heading->textContent)];
            }
        }

        return $headings;
    }

    /**
     * The text, link and attributes of every element a CSS selector matches.
     * Supports tag, .class, #id, [attr], [attr=value], descendant (space) and
     * child (>) combinators, and comma-separated alternatives.
     *
     * @return list<array{text: string, html: string, href: ?string, src: ?string, attributes: array<string, string>}>
     */
    public function select(string $selector, int $limit = 200): array
    {
        $found = [];

        foreach ((new DOMXPath($this->dom))->query(SelectorToXPath::convert($selector)) ?: [] as $element) {
            if (! $element instanceof DOMElement || in_array($element->nodeName, self::DROPPED, true)) {
                continue;
            }

            $attributes = [];

            foreach ($element->attributes as $attribute) {
                $attributes[$attribute->nodeName] = $attribute->nodeValue;
            }

            $href = $element->getAttribute('href');
            $src = $element->getAttribute('src');

            $found[] = [
                'text' => $this->squash($element->textContent),
                'html' => $this->innerHtml($element),
                'href' => $href !== '' ? $this->absolute($href) : null,
                'src' => $src !== '' ? $this->absolute($src) : null,
                'attributes' => $attributes,
            ];

            if (count($found) >= $limit) {
                break;
            }
        }

        return $found;
    }

    private function content(bool $mainOnly): ?DOMNode
    {
        $xpath = new DOMXPath($this->dom);

        if ($mainOnly) {
            foreach (['//main', '//article', '//*[@role="main"]'] as $query) {
                $hit = $xpath->query($query)?->item(0);

                if ($hit !== null && mb_strlen($this->squash($hit->textContent)) > 200) {
                    return $hit;
                }
            }
        }

        return $this->dom->getElementsByTagName('body')->item(0) ?? $this->dom->documentElement;
    }

    private function skip(DOMNode $node, bool $mainOnly): bool
    {
        if (! $node instanceof DOMElement) {
            return false;
        }

        $name = strtolower($node->nodeName);

        return in_array($name, self::DROPPED, true)
            || ($mainOnly && in_array($name, self::FURNITURE, true))
            || $node->hasAttribute('hidden')
            || $node->getAttribute('aria-hidden') === 'true';
    }

    private function plain(DOMNode $node, bool $mainOnly): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return str_replace("\u{a0}", ' ', (string) preg_replace('/\s+/u', ' ', $node->textContent));
        }

        if ($this->skip($node, $mainOnly)) {
            return '';
        }

        $out = '';

        foreach ($node->childNodes as $child) {
            $out .= $this->plain($child, $mainOnly);
        }

        $name = strtolower($node->nodeName);

        return match (true) {
            $name === 'br' => "\n",
            in_array($name, ['p', 'div', 'section', 'article', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'table', 'blockquote', 'pre', 'tr', 'li'], true) => "\n".trim($out)."\n",
            in_array($name, ['td', 'th'], true) => trim($out).' ',
            default => $out,
        };
    }

    private function block(DOMNode $node, bool $mainOnly, int $listDepth = 0): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return str_replace("\u{a0}", ' ', (string) preg_replace('/\s+/u', ' ', $node->textContent));
        }

        if ($this->skip($node, $mainOnly)) {
            return '';
        }

        $name = strtolower($node->nodeName);
        $inner = fn (int $depth = 0): string => $this->children($node, $mainOnly, $depth);

        return match (true) {
            (bool) preg_match('/^h([1-6])$/', $name, $level) => "\n\n".str_repeat('#', (int) $level[1]).' '.$this->squash($inner())."\n\n",
            $name === 'p' => "\n\n".trim($inner())."\n\n",
            $name === 'br' => "  \n",
            $name === 'hr' => "\n\n---\n\n",
            in_array($name, ['strong', 'b'], true) => trim($inner()) === '' ? '' : '**'.trim($inner()).'**',
            in_array($name, ['em', 'i'], true) => trim($inner()) === '' ? '' : '*'.trim($inner()).'*',
            $name === 'code' => '`'.trim($node->textContent).'`',
            $name === 'pre' => "\n\n```\n".trim($node->textContent)."\n```\n\n",
            $name === 'blockquote' => "\n\n".preg_replace('/^/m', '> ', trim($inner()))."\n\n",
            $name === 'a' => $this->anchor($node, trim($inner())),
            $name === 'img' => $node instanceof DOMElement && $node->getAttribute('alt') !== '' ? '!['.$this->squash($node->getAttribute('alt')).']('.($this->absolute($node->getAttribute('src')) ?? '').')' : '',
            in_array($name, ['ul', 'ol'], true) => "\n\n".$this->list($node, $name === 'ol', $mainOnly, $listDepth)."\n\n",
            $name === 'table' => "\n\n".$this->table($node, $mainOnly)."\n\n",
            in_array($name, ['div', 'section', 'article', 'main', 'figure', 'figcaption', 'details', 'summary'], true) => "\n".$inner()."\n",
            default => $inner(),
        };
    }

    private function children(DOMNode $node, bool $mainOnly, int $listDepth = 0): string
    {
        $out = '';

        foreach ($node->childNodes as $child) {
            $out .= $this->block($child, $mainOnly, $listDepth);
        }

        return $out;
    }

    private function anchor(DOMNode $node, string $label): string
    {
        $url = $node instanceof DOMElement ? $this->absolute($node->getAttribute('href')) : null;

        return $url === null || $label === '' ? $label : "[{$label}]({$url})";
    }

    private function list(DOMNode $list, bool $ordered, bool $mainOnly, int $depth): string
    {
        $lines = [];
        $number = 1;

        foreach ($list->childNodes as $item) {
            if (! $item instanceof DOMElement || strtolower($item->nodeName) !== 'li') {
                continue;
            }

            $nested = '';
            $text = '';

            foreach ($item->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), ['ul', 'ol'], true)) {
                    $nested .= "\n".$this->list($child, strtolower($child->nodeName) === 'ol', $mainOnly, $depth + 1);
                } else {
                    $text .= $this->block($child, $mainOnly, $depth);
                }
            }

            $lines[] = str_repeat('  ', $depth).($ordered ? $number++.'. ' : '- ').$this->squash($text).$nested;
        }

        return implode("\n", $lines);
    }

    private function table(DOMNode $table, bool $mainOnly): string
    {
        $rows = [];

        foreach ((new DOMXPath($this->dom))->query('.//tr', $table) ?: [] as $tr) {
            $cells = [];

            foreach ($tr->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                    $cells[] = str_replace('|', '\|', $this->squash($this->children($cell, $mainOnly)));
                }
            }

            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        if ($rows === []) {
            return '';
        }

        $width = max(array_map('count', $rows));
        $line = fn (array $cells): string => '| '.implode(' | ', array_pad($cells, $width, '')).' |';

        return implode("\n", [$line($rows[0]), '|'.str_repeat(' --- |', $width), ...array_map($line, array_slice($rows, 1))]);
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $this->dom->saveHTML($child);
        }

        return trim($html);
    }

    private function squash(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', $text)));
    }

    /**
     * An absolute http(s) or mailto link, or null for anything else
     * (javascript:, data:, a bare fragment).
     */
    private function absolute(string $href): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        if (preg_match('#^(https?://|mailto:)#i', $href) === 1) {
            return $href;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) === 1 || $this->baseUrl === null) {
            return null;
        }

        $base = parse_url($this->baseUrl);

        if (! isset($base['scheme'], $base['host'])) {
            return null;
        }

        $origin = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');

        if (str_starts_with($href, '//')) {
            return $base['scheme'].':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $directory = rtrim(dirname(($base['path'] ?? '/').'x'), '/');

        return $origin.$directory.'/'.$href;
    }
}
