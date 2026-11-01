<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifyUpdateCustomerNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_update_customer';
    }

    public function name(): string
    {
        return 'Shopify: Update Customer';
    }

    public function description(): string
    {
        return 'Changes the fields you fill in on a customer and leaves the rest as they are.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['customer_id'],
            'properties' => [
                ...$this->credentialFields(),
                'customer_id' => Field::dynamic('Customer', 'shopify.customers', null, '207119551'),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'phone' => Field::advanced(Field::text('Phone', 'With the country code, like +14155550123.')),
                'tags' => Field::advanced(Field::list('Tags')),
                'note' => Field::advanced(Field::text('Note')),
                'accepts_marketing' => Field::advanced(Field::select('Email marketing', ['' => 'Leave as it is', 'true' => 'Subscribed', 'false' => 'Not subscribed'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->shopifyId($config, 'customer_id', 'customer id');

        return (array) ($this->shopify($run, $config, 'PUT', "/customers/{$id}.json", ['customer' => [
            'id' => (int) $id,
            ...$this->filled($config, ['email', 'first_name', 'last_name', 'phone', 'note']),
            'tags' => $this->tags($config),
            'accepts_marketing' => filled($config['accepts_marketing'] ?? null) ? filter_var($config['accepts_marketing'], FILTER_VALIDATE_BOOLEAN) : null,
        ]])['customer'] ?? []);
    }
}
