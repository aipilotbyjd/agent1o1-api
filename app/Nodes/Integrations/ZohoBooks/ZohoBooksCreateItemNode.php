<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoBooksCreateItemNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_create_item';
    }

    public function name(): string
    {
        return 'Zoho Books: Create Item';
    }

    public function description(): string
    {
        return 'Adds a product or service with its price.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'rate'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Consulting hour'),
                'rate' => Field::text('Price', null, '120.00'),
                'description' => Field::advanced(Field::textarea('Description')),
                'sku' => Field::advanced(Field::text('SKU')),
                'unit' => Field::advanced(Field::text('Unit', null, 'hr')),
                'type' => Field::advanced(Field::select('Type', ['service' => 'Service', 'goods' => 'Goods'], null, 'service')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rate = (string) ($config['rate'] ?? '');

        if (! is_numeric($rate) || (float) $rate < 0) {
            throw new InvalidArgumentException('The price must be a number.');
        }

        $item = $this->books($run, $config, 'POST', '/items', [], array_filter([
            'name' => $this->requiredString($config, 'name', 'The name'),
            'rate' => (float) $rate,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'sku' => filled($config['sku'] ?? null) ? trim((string) $config['sku']) : null,
            'unit' => filled($config['unit'] ?? null) ? trim((string) $config['unit']) : null,
            'product_type' => ($config['type'] ?? 'service') === 'goods' ? 'goods' : 'service',
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeItem((array) ($item['item'] ?? []));
    }
}
