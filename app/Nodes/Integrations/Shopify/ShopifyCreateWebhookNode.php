<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ShopifyCreateWebhookNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_create_webhook';
    }

    public function name(): string
    {
        return 'Shopify: Create Webhook';
    }

    public function description(): string
    {
        return 'Sets up a webhook that posts a topic such as orders/create to an https address.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['topic', 'address'],
            'properties' => [
                ...$this->credentialFields(),
                'topic' => Field::text('Topic', 'Such as orders/create or customers/update.', 'orders/create'),
                'address' => Field::text('Address', 'Must be https.', 'https://example.com/shopify'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $topic = $this->requiredString($config, 'topic', 'The topic');
        $address = $this->requiredString($config, 'address', 'The address');

        if (preg_match('~^[a-z_]+/[a-z_]+$~', $topic) !== 1) {
            throw new InvalidArgumentException('A topic looks like orders/create.');
        }

        if (! str_starts_with($address, 'https://')) {
            throw new InvalidArgumentException('The address must start with https://.');
        }

        return (array) ($this->shopify($run, $config, 'POST', '/webhooks.json', ['webhook' => ['topic' => $topic, 'address' => $address, 'format' => 'json']])['webhook'] ?? []);
    }
}
