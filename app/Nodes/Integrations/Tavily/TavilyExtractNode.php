<?php

namespace App\Nodes\Integrations\Tavily;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Agents\UntrustedContent;
use InvalidArgumentException;

class TavilyExtractNode extends AbstractTavilyNode
{
    private const int MAX_URLS = 10;

    public function type(): string
    {
        return 'tavily_extract';
    }

    public function name(): string
    {
        return 'Tavily: Extract Pages';
    }

    public function description(): string
    {
        return 'Reads the full text of up to ten web pages, including ones a plain fetch cannot, and returns each as clean text.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['urls'],
            'properties' => [
                ...$this->credentialFields(),
                'urls' => Field::list('Pages', 'The web addresses to read (up to '.self::MAX_URLS.'). Use {{templates}} to take them from an earlier step.', 'https://example.com/article'),
                'depth' => Field::advanced(Field::select('Depth', ['basic' => 'Basic', 'advanced' => 'Advanced (tables and embedded content, costs more)'], null, 'basic')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'pages' => ['title' => 'Pages', 'description' => 'Each page: url and content.'],
                'context' => ['title' => 'Context', 'description' => 'All the pages as one block of text for an AI prompt, marked as outside content so the AI treats it as data.'],
                'failed' => ['title' => 'Failed', 'description' => 'Pages that could not be read, with the reason.'],
                'count' => ['title' => 'Count', 'description' => 'How many pages were read.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $urls = array_values(array_filter($this->strings($config, 'urls'), fn (string $url): bool => preg_match('#^https?://#i', $url) === 1));

        if ($urls === []) {
            throw new InvalidArgumentException('Give at least one full web address starting with http:// or https://.');
        }

        if (count($urls) > self::MAX_URLS) {
            throw new InvalidArgumentException('Read at most '.self::MAX_URLS.' pages at a time.');
        }

        $body = $this->call($run, $config, 'POST', self::API_URL.'/extract', [
            'urls' => $urls,
            'extract_depth' => ($config['depth'] ?? 'basic') === 'advanced' ? 'advanced' : 'basic',
        ]);

        $pages = array_values(array_filter(array_map(fn (mixed $page): ?array => is_array($page) && filled($page['url'] ?? null) ? ['url' => (string) $page['url'], 'content' => trim((string) ($page['raw_content'] ?? ''))] : null, (array) ($body['results'] ?? []))));

        return [
            'pages' => $pages,
            'context' => $pages === [] ? '' : UntrustedContent::wrap('web_pages', implode("\n\n---\n\n", array_map(fn (array $page): string => "{$page['url']}\n{$page['content']}", $pages))),
            'failed' => array_values(array_filter(array_map(fn (mixed $page): ?array => is_array($page) ? ['url' => (string) ($page['url'] ?? ''), 'error' => (string) ($page['error'] ?? 'Could not be read.')] : null, (array) ($body['failed_results'] ?? [])))),
            'count' => count($pages),
        ];
    }
}
