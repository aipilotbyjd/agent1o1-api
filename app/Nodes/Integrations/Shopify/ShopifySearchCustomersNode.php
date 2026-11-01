<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifySearchCustomersNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_search_customers';
    }

    public function name(): string
    {
        return 'Shopify: Search Customers';
    }

    public function description(): string
    {
        return 'Finds customers by name, email, phone, tag or city, such as email:ada@example.com or tag:vip.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Query', 'Words to find, or field:value such as email:ada@example.com, tag:vip, country:Canada.', 'email:ada@example.com'),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOutput($run, $config, '/customers/search.json', 'customers', ['query' => $this->requiredString($config, 'query', 'The query')]);
    }
}
