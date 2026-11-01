<?php

namespace App\Nodes\Integrations\BraveSearch;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Nodes\Integrations\Concerns\CallsApiKeyService;
use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * Shared by the Brave Search nodes: the key goes in the X-Subscription-Token
 * header, and a search needs a query and optionally a country, language,
 * freshness window and safe-search level.
 */
abstract class AbstractBraveSearchNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    use CallsApiKeyService;

    protected const string API_URL = 'https://api.search.brave.com/res/v1';

    public function category(): string
    {
        return 'brave_search';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    protected function serviceName(): string
    {
        return 'Brave Search';
    }

    protected function authenticate(string $key, array $headers, array $query): array
    {
        return [[...$headers, 'X-Subscription-Token' => $key], $query];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function searchFields(): array
    {
        return [
            ...$this->credentialFields(),
            'query' => Field::text('Search for', 'What to search for. Use {{templates}} to take it from an earlier step.', 'best project management tools'),
            'count' => Field::integer('Most results', null, 10, 1, 20),
            'country' => Field::advanced(Field::text('Country', 'A two-letter country code to favour, e.g. US or GB.', 'US')),
            'language' => Field::advanced(Field::text('Language', 'A language code for the results, e.g. en.', 'en')),
            'freshness' => Field::advanced(Field::select('Published', ['any' => 'Any time', 'pd' => 'Past day', 'pw' => 'Past week', 'pm' => 'Past month', 'py' => 'Past year'], null, 'any')),
            'safesearch' => Field::advanced(Field::select('Safe search', ['off' => 'Off', 'moderate' => 'Moderate', 'strict' => 'Strict'], null, 'moderate')),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function searchQuery(array $config): array
    {
        $query = trim((string) ($config['query'] ?? ''));

        if ($query === '') {
            throw new InvalidArgumentException('What to search for is empty.');
        }

        if (mb_strlen($query) > 400) {
            throw new InvalidArgumentException('The search is too long (the limit is 400 characters).');
        }

        $freshness = (string) ($config['freshness'] ?? 'any');

        return [
            'q' => $query,
            'count' => max(1, min(20, (int) ($config['count'] ?? 10))),
            'country' => filled($config['country'] ?? null) ? strtoupper(trim((string) $config['country'])) : null,
            'search_lang' => filled($config['language'] ?? null) ? strtolower(trim((string) $config['language'])) : null,
            'freshness' => in_array($freshness, ['pd', 'pw', 'pm', 'py'], true) ? $freshness : null,
            'safesearch' => in_array($config['safesearch'] ?? 'moderate', ['off', 'moderate', 'strict'], true) ? $config['safesearch'] ?? 'moderate' : 'moderate',
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'results' => ['title' => 'Results', 'description' => 'Each result: title, url, description, age and source.'],
                'urls' => ['title' => 'Addresses', 'description' => 'Just the web addresses, as a list.'],
                'count' => ['title' => 'Count', 'description' => 'How many results came back.'],
                'query' => ['title' => 'Search', 'description' => 'What was searched for.'],
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, mixed>
     */
    protected function shape(array $items, string $query): array
    {
        $results = array_values(array_filter(array_map(fn (mixed $item): ?array => is_array($item) && filled($item['url'] ?? null) ? [
            'title' => trim(strip_tags((string) ($item['title'] ?? ''))),
            'url' => (string) $item['url'],
            'description' => trim(html_entity_decode(strip_tags((string) ($item['description'] ?? '')), ENT_QUOTES | ENT_HTML5)),
            'age' => $item['age'] ?? $item['page_age'] ?? null,
            'source' => $item['meta_url']['hostname'] ?? $item['profile']['name'] ?? parse_url((string) $item['url'], PHP_URL_HOST) ?: null,
        ] : null, $items)));

        return ['results' => $results, 'urls' => array_column($results, 'url'), 'count' => count($results), 'query' => $query];
    }
}
