<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyDeleteWebhookNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_delete_webhook';
    }

    public function name(): string
    {
        return 'Shopify: Delete Webhook';
    }

    public function description(): string
    {
        return 'Removes a webhook.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webhook_id'],
            'properties' => [
                ...$this->credentialFields(),
                'webhook_id' => Field::text('Webhook ID', null, '4759306'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->shopifyId($config, 'webhook_id', 'webhook id');
        $this->shopify($run, $config, 'DELETE', "/webhooks/{$id}.json");

        return ['deleted' => true, 'webhook_id' => $id];
    }
}
