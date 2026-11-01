<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksListItemsNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_list_items';
    }

    public function name(): string
    {
        return 'Zoho Books: List Items';
    }

    public function description(): string
    {
        return 'Lists the products and services you sell.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'search' => Field::advanced(Field::text('Name contains')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $items = $this->listAll($run, $config, '/items', 'items', array_filter([
            'name_contains' => filled($config['search'] ?? null) ? trim((string) $config['search']) : null,
        ], fn (mixed $value): bool => $value !== null), $this->limit($config, 50));

        return ['items' => array_map($this->shapeItem(...), $items), 'count' => count($items)];
    }
}
