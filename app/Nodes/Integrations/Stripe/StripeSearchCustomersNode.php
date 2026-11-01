<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeSearchCustomersNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_search_customers';
    }

    public function name(): string
    {
        return 'Stripe: Search Customers';
    }

    public function description(): string
    {
        return 'Finds customers with Stripe\'s search syntax, such as email:\'ada@example.com\' or name~\'ada\'. New customers can take about a minute to show up.';
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
                'query' => Field::text('Query', 'Stripe search syntax: field:\'value\' for an exact match, field~\'part\' for a part of it, joined with AND or OR.', "email:'ada@example.com'"),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = $this->requiredString($config, 'query', 'The query');
        $limit = $this->limit($config);
        $customers = [];
        $page = null;

        do {
            $result = $this->stripe($run, $config, 'GET', '/customers/search', ['query' => $query, 'limit' => min(self::PAGE, $limit - count($customers)), 'page' => $page]);
            array_push($customers, ...array_values(array_filter((array) ($result['data'] ?? []), 'is_array')));
            $page = $result['next_page'] ?? null;
            $more = (bool) ($result['has_more'] ?? false);
        } while ($more && is_string($page) && count($customers) < $limit);

        return ['customers' => array_slice($customers, 0, $limit), 'count' => min(count($customers), $limit), 'has_more' => $more || count($customers) > $limit];
    }
}
