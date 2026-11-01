<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ShopifyCreateCustomerNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_create_customer';
    }

    public function name(): string
    {
        return 'Shopify: Create Customer';
    }

    public function description(): string
    {
        return 'Creates a customer. Needs an email address or a phone number.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
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
        if (blank($config['email'] ?? null) && blank($config['phone'] ?? null)) {
            throw new InvalidArgumentException('A customer needs an email address or a phone number.');
        }

        return (array) ($this->shopify($run, $config, 'POST', '/customers.json', ['customer' => [
            ...$this->filled($config, ['email', 'first_name', 'last_name', 'phone', 'note']),
            'tags' => $this->tags($config),
            'accepts_marketing' => filled($config['accepts_marketing'] ?? null) ? filter_var($config['accepts_marketing'], FILTER_VALIDATE_BOOLEAN) : null,
        ]])['customer'] ?? []);
    }
}
