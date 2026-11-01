<?php

namespace App\Nodes\Integrations\BraveSearch;

use App\Models\Runs\Run;

class BraveSearchNewsNode extends AbstractBraveSearchNode
{
    public function type(): string
    {
        return 'brave_search_news';
    }

    public function name(): string
    {
        return 'Brave Search: Search News';
    }

    public function description(): string
    {
        return 'Searches recent news articles and returns the headlines, addresses, sources and how old each is.';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'required' => ['query'], 'properties' => $this->searchFields()];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = $this->searchQuery($config);
        $body = $this->call($run, $config, 'GET', self::API_URL.'/news/search', query: $query);

        return $this->shape((array) ($body['results'] ?? []), $query['q']);
    }
}
