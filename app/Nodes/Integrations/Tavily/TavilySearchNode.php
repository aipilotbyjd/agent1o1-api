<?php

namespace App\Nodes\Integrations\Tavily;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Agents\UntrustedContent;
use InvalidArgumentException;

class TavilySearchNode extends AbstractTavilyNode
{
    public function type(): string
    {
        return 'tavily_search';
    }

    public function name(): string
    {
        return 'Tavily: Search';
    }

    public function description(): string
    {
        return 'Searches the web and returns the relevant text of each page, plus an optional short answer. Built to feed an AI step.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search for', 'A question or topic. Use {{templates}} to take it from an earlier step.', 'latest EU AI Act obligations for small businesses'),
                'topic' => Field::select('Kind of search', ['general' => 'General', 'news' => 'News'], null, 'general'),
                'depth' => Field::select('Depth', ['basic' => 'Basic (faster, cheaper)', 'advanced' => 'Advanced (more thorough, costs more)'], null, 'basic'),
                'max_results' => Field::integer('Most results', null, 5, 1, 20),
                'include_answer' => Field::boolean('Include a short answer', 'Also return a one-paragraph answer written from the results.', false),
                'include_domains' => Field::advanced(Field::list('Only these sites', 'Optional. Search only these sites, e.g. gov.uk.', 'gov.uk')),
                'exclude_domains' => Field::advanced(Field::list('Skip these sites', 'Optional.', 'pinterest.com')),
                'days' => Field::advanced(Field::integer('News from the last (days)', 'News search only.', null, 1, 365)),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'results' => ['title' => 'Results', 'description' => 'Each result: title, url, content (the relevant text) and score.'],
                'answer' => ['title' => 'Answer', 'description' => 'A short answer written from the results, when asked for.'],
                'context' => ['title' => 'Context', 'description' => 'The results as one block of text for an AI prompt, marked as outside content so the AI treats it as data.'],
                'urls' => ['title' => 'Addresses', 'description' => 'Just the web addresses, as a list.'],
                'count' => ['title' => 'Count', 'description' => 'How many results came back.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = trim((string) ($config['query'] ?? ''));

        if ($query === '') {
            throw new InvalidArgumentException('What to search for is empty.');
        }

        $topic = ($config['topic'] ?? 'general') === 'news' ? 'news' : 'general';
        $body = $this->call($run, $config, 'POST', self::API_URL.'/search', array_filter([
            'query' => mb_substr($query, 0, 400),
            'topic' => $topic,
            'search_depth' => ($config['depth'] ?? 'basic') === 'advanced' ? 'advanced' : 'basic',
            'max_results' => max(1, min(20, (int) ($config['max_results'] ?? 5))),
            'include_answer' => (bool) ($config['include_answer'] ?? false),
            'include_domains' => $this->strings($config, 'include_domains') ?: null,
            'exclude_domains' => $this->strings($config, 'exclude_domains') ?: null,
            'days' => $topic === 'news' && filled($config['days'] ?? null) ? max(1, (int) $config['days']) : null,
        ], fn (mixed $value): bool => $value !== null));

        $results = array_values(array_filter(array_map(fn (mixed $result): ?array => is_array($result) && filled($result['url'] ?? null) ? [
            'title' => trim((string) ($result['title'] ?? '')),
            'url' => (string) $result['url'],
            'content' => trim((string) ($result['content'] ?? '')),
            'score' => round((float) ($result['score'] ?? 0), 4),
        ] : null, (array) ($body['results'] ?? []))));

        return [
            'results' => $results,
            'answer' => filled($body['answer'] ?? null) ? trim((string) $body['answer']) : null,
            'context' => $results === [] ? '' : UntrustedContent::wrap('web_search', implode("\n\n---\n\n", array_map(fn (array $result): string => "{$result['title']}\n{$result['url']}\n{$result['content']}", $results))),
            'urls' => array_column($results, 'url'),
            'count' => count($results),
        ];
    }
}
