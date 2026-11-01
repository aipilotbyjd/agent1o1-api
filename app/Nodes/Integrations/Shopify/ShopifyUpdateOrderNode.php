<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ShopifyUpdateOrderNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_update_order';
    }

    public function name(): string
    {
        return 'Shopify: Update Order';
    }

    public function description(): string
    {
        return 'Changes the note, tags, email or phone of an order and leaves the rest as it is.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['order_id'],
            'properties' => [
                ...$this->credentialFields(),
                'order_id' => Field::text('Order ID', 'The number in the order\'s admin link, not the #1001 name.', '450789469'),
                'note' => Field::text('Note'),
                'tags' => Field::list('Tags', 'Replaces the order\'s tags.'),
                'email' => Field::text('Email'),
                'phone' => Field::advanced(Field::text('Phone')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->shopifyId($config, 'order_id', 'order id');
        $changes = [...$this->filled($config, ['note', 'email', 'phone']), 'tags' => $this->tags($config)];

        if (array_filter($changes, fn (mixed $value): bool => $value !== null) === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return (array) ($this->shopify($run, $config, 'PUT', "/orders/{$id}.json", ['order' => ['id' => (int) $id, ...$changes]])['order'] ?? []);
    }
}
