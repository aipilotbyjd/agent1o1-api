<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionSearchNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_search';
    }

    public function name(): string
    {
        return 'Notion: Search';
    }

    public function description(): string
    {
        return 'Finds pages and databases the connection can see, by title.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search text', 'Matches titles. Leave empty to list the most recently edited.'),
                'object' => Field::select('Look for', ['all' => 'Pages and databases', 'page' => 'Pages', 'database' => 'Databases'], default: 'all'),
                'order' => Field::select('Order', ['descending' => 'Most recently edited first', 'ascending' => 'Least recently edited first'], default: 'descending'),
                'limit' => Field::integer('Limit', 'The most results to return (up to 200).', 25, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = $config['object'] ?? 'all';
        $results = $this->searchObjects(
            $run,
            $config,
            in_array($object, ['page', 'database'], true) ? $object : null,
            trim((string) ($config['query'] ?? '')),
            ($config['order'] ?? 'descending') === 'ascending' ? 'ascending' : 'descending',
            max(1, min(200, (int) ($config['limit'] ?? 25))),
        );

        return ['results' => $results, 'count' => count($results)];
    }
}
