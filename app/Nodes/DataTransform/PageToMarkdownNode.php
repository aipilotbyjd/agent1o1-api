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
use App\Services\Agents\UntrustedContent;
use App\Services\Web\HtmlDocument;
use App\Services\Web\WebFetcher;

/**
 * Opens a public web page and returns it as clean Markdown and text, ready
 * for an AI step: no menus, scripts or styles, links kept, length bounded.
 */
class PageToMarkdownNode implements DeclaresEffect, DeclaresOutputSchema, HasIcon, NodeContract
{
    private const int DEFAULT_MAX_CHARACTERS = 30000;

    public function __construct(private readonly WebFetcher $fetcher) {}

    public function type(): string
    {
        return 'page_to_markdown';
    }

    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    public function name(): string
    {
        return 'Page to Markdown';
    }

    public function icon(): string
    {
        return 'link-01';
    }

    public function description(): string
    {
        return 'Opens a public web page and returns its title and content as clean Markdown and text, ready to hand to an AI step.';
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
                'url' => [...Field::text('Page address', 'A public web page. Use {{templates}} to take it from an earlier step.', 'https://example.com/blog/post'), 'format' => 'uri'],
                'main_content_only' => Field::advanced(Field::boolean('Main content only', 'Leave out menus, headers, footers and sidebars.', true)),
                'max_characters' => Field::advanced(Field::integer('Longest result', 'Cut the Markdown and text off at this many characters, so a huge page cannot flood an AI prompt.', self::DEFAULT_MAX_CHARACTERS, 500, 200000)),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['title' => 'Title', 'description' => 'The page title.'],
                'description' => ['title' => 'Description', 'description' => 'The page’s own summary, when it has one.'],
                'markdown' => ['title' => 'Markdown', 'description' => 'The content as Markdown.'],
                'text' => ['title' => 'Text', 'description' => 'The content as plain text.'],
                'context' => ['title' => 'Context', 'description' => 'The Markdown wrapped as outside content, for an AI prompt, so the AI treats the page as data and not as instructions.'],
                'headings' => ['title' => 'Headings', 'description' => 'The page’s headings, with their level.'],
                'links' => ['title' => 'Links', 'description' => 'The links in the content: text and url.'],
                'truncated' => ['title' => 'Truncated', 'description' => 'True when the page was longer than the limit and was cut off.'],
                'url' => ['title' => 'Address', 'description' => 'The address that was read.'],
                'status' => ['title' => 'Status', 'description' => 'The HTTP status of the page.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $page = $this->fetcher->page((string) ($config['url'] ?? ''));
        $document = HtmlDocument::parse($page['body'], $page['url']);
        $main = (bool) ($config['main_content_only'] ?? true);
        $max = max(500, min(200000, (int) ($config['max_characters'] ?? self::DEFAULT_MAX_CHARACTERS)));

        $markdown = $document->markdown($main);
        $text = $document->text($main);
        $truncated = mb_strlen($markdown) > $max || mb_strlen($text) > $max;

        if ($truncated) {
            $markdown = rtrim(mb_substr($markdown, 0, $max)).' […]';
            $text = rtrim(mb_substr($text, 0, $max)).' […]';
        }

        return [
            'title' => $document->title(),
            'description' => $document->meta()['description'] ?? $document->meta()['og:description'] ?? '',
            'markdown' => $markdown,
            'text' => $text,
            'context' => UntrustedContent::wrap('web_page', $markdown),
            'headings' => $document->headings(),
            'links' => $document->links($main),
            'truncated' => $truncated,
            'url' => $page['url'],
            'status' => $page['status'],
        ];
    }
}
