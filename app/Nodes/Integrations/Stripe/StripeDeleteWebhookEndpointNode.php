<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeDeleteWebhookEndpointNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_delete_webhook_endpoint';
    }

    public function name(): string
    {
        return 'Stripe: Delete Webhook Endpoint';
    }

    public function description(): string
    {
        return 'Removes a webhook endpoint.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['endpoint_id'],
            'properties' => [
                ...$this->credentialFields(),
                'endpoint_id' => Field::text('Endpoint ID', null, 'we_...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $endpoint = $this->stripeId($config, 'endpoint_id', 'we', 'webhook endpoint id');
        $result = $this->stripe($run, $config, 'DELETE', "/webhook_endpoints/{$endpoint}");

        return ['deleted' => (bool) ($result['deleted'] ?? false), 'endpoint_id' => $endpoint];
    }
}
