<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeListWebhookEndpointsNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_list_webhook_endpoints';
    }

    public function name(): string
    {
        return 'Stripe: List Webhook Endpoints';
    }

    public function description(): string
    {
        return 'Lists the webhook endpoints set up on the account.';
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
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOutput($run, $config, '/webhook_endpoints', 'webhook_endpoints', []);
    }
}
