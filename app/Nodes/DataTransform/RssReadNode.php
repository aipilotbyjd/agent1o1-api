<?php

namespace App\Nodes\DataTransform;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use App\Services\Web\WebFetcher;
use Carbon\Carbon;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Reads an RSS, Atom or RDF feed: a blog, a news site, a changelog, a
 * podcast, a job board. Switched on as a trigger it starts the flow for each
 * new entry.
 */
class RssReadNode implements DeclaresEffect, DeclaresOutputSchema, HasIcon, NodeContract, SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int MAX_ITEMS = 200;

    private const int TRIGGER_ITEMS = 50;

    public function __construct(private readonly WebFetcher $fetcher) {}

    public function type(): string
    {
        return 'rss_read';
    }

    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    public function name(): string
    {
        return 'RSS Feed';
    }

    public function icon(): string
    {
        return 'rss';
    }

    public function description(): string
    {
        return 'Reads an RSS or Atom feed and returns its entries, newest first. As a trigger, it starts the flow for each new entry.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                'url' => [...Field::text('Feed address', 'The feed’s web address, usually ending in /feed, /rss or .xml.', 'https://example.com/feed'), 'format' => 'uri'],
                'limit' => Field::integer('Most entries', null, 20, 1, self::MAX_ITEMS),
                'keyword' => Field::advanced(Field::text('Only entries mentioning', 'Optional. Keep entries whose title or summary contains this word or phrase.')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => ['title' => 'Entries', 'description' => 'Each entry: id, title, link, summary, content, published, author, categories and image.'],
                'count' => ['title' => 'Count', 'description' => 'How many entries were returned.'],
                'feed_title' => ['title' => 'Feed title', 'description' => 'The feed’s name.'],
                'feed_link' => ['title' => 'Feed link', 'description' => 'The website the feed belongs to.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $page = $this->fetcher->feed((string) ($config['url'] ?? ''));
        [$title, $link, $entries] = $this->parse($page['body']);

        $keyword = mb_strtolower(trim((string) ($config['keyword'] ?? '')));

        if ($keyword !== '') {
            $entries = array_values(array_filter($entries, fn (array $entry): bool => str_contains(mb_strtolower($entry['title'].' '.$entry['summary']), $keyword)));
        }

        usort($entries, fn (array $a, array $b): int => strcmp((string) $b['published'], (string) $a['published']));
        $entries = array_slice($entries, 0, max(1, min(self::MAX_ITEMS, (int) ($config['limit'] ?? 20))));

        return ['items' => $entries, 'count' => count($entries), 'feed_title' => $title, 'feed_link' => $link];
    }

    /**
     * @return array{0: string, 1: string, 2: list<array<string, mixed>>}
     */
    private function parse(string $xml): array
    {
        // A feed has no need for a document type; refusing one rules out entity-expansion tricks.
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml) === 1) {
            throw new RuntimeException('That feed uses features that are not allowed, so it was not read.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $root = simplexml_load_string(trim($xml), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } catch (Throwable) {
            $root = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($root === false) {
            throw new RuntimeException('That address did not return a feed it could read. Check that it is an RSS or Atom feed.');
        }

        $name = strtolower($root->getName());

        if ($name === 'feed') {
            return $this->atom($root);
        }

        if (in_array($name, ['rss', 'rdf'], true)) {
            return $this->rss($root);
        }

        throw new RuntimeException('That address returned something that is not an RSS or Atom feed.');
    }

    /**
     * @return array{0: string, 1: string, 2: list<array<string, mixed>>}
     */
    private function rss(SimpleXMLElement $root): array
    {
        $channel = $root->channel ?? $root;
        $items = [];

        foreach ($root->xpath('//*[local-name()="item"]') ?: [] as $item) {
            $content = $item->children('http://purl.org/rss/1.0/modules/content/');
            $dc = $item->children('http://purl.org/dc/elements/1.1/');
            $media = $item->children('http://search.yahoo.com/mrss/');
            $link = trim((string) $item->link);
            $summary = $this->clean($this->text($item->description));

            $items[] = [
                'id' => trim((string) $item->guid) ?: ($link ?: sha1((string) $item->title)),
                'title' => $this->clean($this->text($item->title)),
                'link' => $link,
                'summary' => mb_substr($summary, 0, 1000),
                'content' => $this->clean($this->text($content->encoded)) ?: $summary,
                'published' => $this->date((string) ($item->pubDate ?? $dc->date ?? '')),
                'author' => trim((string) ($item->author ?? $dc->creator ?? '')),
                'categories' => array_values(array_filter(array_map(fn (mixed $category): string => trim((string) $category), iterator_to_array($item->category ?? [], false)))),
                'image' => $this->url((string) ($item->enclosure['url'] ?? $media->content['url'] ?? $media->thumbnail['url'] ?? '')),
            ];
        }

        return [$this->clean($this->text($channel->title)), trim((string) ($channel->link ?? '')), $items];
    }

    /**
     * @return array{0: string, 1: string, 2: list<array<string, mixed>>}
     */
    private function atom(SimpleXMLElement $root): array
    {
        $items = [];

        foreach ($root->entry as $entry) {
            $link = '';

            foreach ($entry->link as $candidate) {
                if (($candidate['rel'] ?? 'alternate') == 'alternate' || $link === '') {
                    $link = trim((string) $candidate['href']);
                }
            }

            $summary = $this->clean($this->text($entry->summary));

            $items[] = [
                'id' => trim((string) $entry->id) ?: ($link ?: sha1((string) $entry->title)),
                'title' => $this->clean($this->text($entry->title)),
                'link' => $link,
                'summary' => mb_substr($summary ?: $this->clean($this->text($entry->content)), 0, 1000),
                'content' => $this->clean($this->text($entry->content)) ?: $summary,
                'published' => $this->date((string) ($entry->published ?? $entry->updated ?? '')),
                'author' => trim((string) ($entry->author->name ?? '')),
                'categories' => array_values(array_filter(array_map(fn (mixed $category): string => trim((string) $category['term']), iterator_to_array($entry->category ?? [], false)))),
                'image' => null,
            ];
        }

        $siteLink = '';

        foreach ($root->link as $candidate) {
            if (($candidate['rel'] ?? 'alternate') == 'alternate') {
                $siteLink = trim((string) $candidate['href']);
            }
        }

        return [$this->clean($this->text($root->title)), $siteLink, $items];
    }

    /**
     * An element's whole text, including any markup written inline inside it
     * (feeds that do not escape their HTML); an absent element is empty.
     */
    private function text(?SimpleXMLElement $element): string
    {
        if ($element === null || ($element->count() === 0 && (string) $element === '')) {
            return '';
        }

        $node = dom_import_simplexml($element);

        return $node === null ? (string) $element : $node->textContent;
    }

    private function clean(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function date(string $value): ?string
    {
        try {
            return trim($value) === '' ? null : Carbon::parse($value)->utc()->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private function url(string $value): ?string
    {
        return preg_match('#^https?://#i', trim($value)) === 1 ? trim($value) : null;
    }

    protected function triggerItemsPath(): ?string
    {
        return 'items';
    }

    public function triggerName(): string
    {
        return 'RSS: New entry';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow for each new entry in an RSS or Atom feed, such as a new blog post, release or job listing.';
    }

    public function triggerPollMinutes(): int
    {
        return 15;
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => self::TRIGGER_ITEMS];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
