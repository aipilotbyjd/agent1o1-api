<?php

namespace App\Nodes\Integrations\BraveSearch;

use App\Models\Runs\Run;

class BraveSearchWebNode extends AbstractBraveSearchNode
{
    public function type(): string
    {
        return 'brave_search_web';
    }

    public function name(): string
    {
        return 'Brave Search: Search Web';
    }

    public function description(): string
    {
        return 'Searches the web and returns the top results: title, address and a short description of each.';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'required' => ['query'], 'properties' => $this->searchFields()];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = $this->searchQuery($config);
        $body = $this->call($run, $config, 'GET', self::API_URL.'/web/search', query: $query);

        return $this->shape((array) ($body['web']['results'] ?? []), $query['q']);
    }
}
