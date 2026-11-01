<?php

namespace App\Nodes\DataTransform;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Web\HtmlDocument;
use App\Services\Web\SelectorToXPath;
use App\Services\Web\WebFetcher;
use InvalidArgumentException;

/**
 * Reads a public web page and returns the parts a CSS selector picks out:
 * every product name, every link in a list, every headline. For the whole
 * page as clean text, use Page to Markdown.
 */
class WebScrapeNode implements DeclaresEffect, DeclaresOutputSchema, HasIcon, NodeContract
{
    private const int MAX_ITEMS = 500;

    public function __construct(private readonly WebFetcher $fetcher) {}

    public function type(): string
    {
        return 'web_scrape';
    }

    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    public function name(): string
    {
        return 'Web Scrape';
    }

    public function icon(): string
    {
        return 'globe';
    }

    public function description(): string
    {
        return 'Opens a public web page and returns the parts you pick with a CSS selector, such as every headline, product name or link.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url', 'selector'],
            'properties' => [
                'url' => [...Field::text('Page address', 'A public web page. Use {{templates}} to take it from an earlier step.', 'https://example.com/products'), 'format' => 'uri'],
                'selector' => Field::text('What to pick', 'A CSS selector: tags, .classes, #ids and [attributes], with a space or > between them. e.g. h2.title, .product > a, a[href^="/blog"].', 'h2.title'),
                'limit' => Field::integer('Most items', null, 100, 1, self::MAX_ITEMS),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => ['title' => 'Items', 'description' => 'Everything the selector matched: text, html, href (a link’s address), src (an image’s address) and attributes.'],
                'texts' => ['title' => 'Texts', 'description' => 'Just the text of each item, as a list.'],
                'count' => ['title' => 'Count', 'description' => 'How many items matched.'],
                'title' => ['title' => 'Page title', 'description' => 'The title of the page.'],
                'status' => ['title' => 'Status', 'description' => 'The HTTP status of the page.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $selector = trim((string) ($config['selector'] ?? ''));

        if ($selector === '') {
            throw new InvalidArgumentException('Say what to pick with a CSS selector.');
        }

        // A selector that cannot work is refused before any page is fetched.
        SelectorToXPath::convert($selector);

        $page = $this->fetcher->page((string) ($config['url'] ?? ''));
        $document = HtmlDocument::parse($page['body'], $page['url']);
        $items = $document->select($selector, max(1, min(self::MAX_ITEMS, (int) ($config['limit'] ?? 100))));

        return [
            'items' => $items,
            'texts' => array_column($items, 'text'),
            'count' => count($items),
            'title' => $document->title(),
            'status' => $page['status'],
        ];
    }
}
