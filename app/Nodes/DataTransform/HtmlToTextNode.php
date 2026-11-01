<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Web\HtmlDocument;
use InvalidArgumentException;

/**
 * Reads HTML you already have, such as an email body or a stored page, into
 * plain text or Markdown.
 */
class HtmlToTextNode extends PureDataNode
{
    public function type(): string
    {
        return 'html_to_text';
    }

    public function name(): string
    {
        return 'HTML to Text';
    }

    public function icon(): string
    {
        return 'html-5';
    }

    public function description(): string
    {
        return 'Turns HTML, such as an email body or a saved page, into clean text or Markdown, with its title and links.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['html'],
            'properties' => [
                'html' => Field::textarea('HTML', 'Use {{templates}} to take it from an earlier step.'),
                'base_url' => Field::advanced([...Field::text('Page address', 'Used to turn relative links into full ones.', 'https://example.com/blog/post'), 'format' => 'uri']),
                'main_content_only' => Field::advanced(Field::boolean('Main content only', 'Leave out menus, headers, footers and sidebars.', true)),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['title' => 'Text', 'description' => 'The readable text.'],
                'markdown' => ['title' => 'Markdown', 'description' => 'The content as Markdown, keeping headings, lists, links and tables.'],
                'title' => ['title' => 'Title', 'description' => 'The page title.'],
                'links' => ['title' => 'Links', 'description' => 'Every link: text and url.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $html = $this->text($config, 'html');

        if (trim($html) === '') {
            throw new InvalidArgumentException('There is no HTML to read.');
        }

        if (strlen($html) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('The HTML is too large to read.');
        }

        $document = HtmlDocument::parse($html, filled($config['base_url'] ?? null) ? (string) $config['base_url'] : null);
        $main = (bool) ($config['main_content_only'] ?? true);

        return ['text' => $document->text($main), 'markdown' => $document->markdown($main), 'title' => $document->title(), 'links' => $document->links($main)];
    }
}
