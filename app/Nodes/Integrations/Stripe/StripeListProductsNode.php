<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeListProductsNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_list_products';
    }

    public function name(): string
    {
        return 'Stripe: List Products';
    }

    public function description(): string
    {
        return 'Lists products, newest first.';
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
                'active' => Field::select('Status', ['' => 'Any', 'true' => 'Active', 'false' => 'Archived']),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOutput($run, $config, '/products', 'products', [
            'active' => filled($config['active'] ?? null) ? filter_var($config['active'], FILTER_VALIDATE_BOOLEAN) : null,
        ]);
    }
}
