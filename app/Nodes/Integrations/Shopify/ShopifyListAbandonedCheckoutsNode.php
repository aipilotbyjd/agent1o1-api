<?php

namespace App\Nodes\Integrations\Shopify;

use App\Nodes\Support\Field;

class ShopifyListAbandonedCheckoutsNode extends AbstractShopifyTriggerNode
{
    public function type(): string
    {
        return 'shopify_list_abandoned_checkouts';
    }

    public function name(): string
    {
        return 'Shopify: List Abandoned Checkouts';
    }

    public function description(): string
    {
        return 'Lists checkouts customers started and did not finish, newest first, with their cart and email. As a trigger it starts the flow once for each abandoned checkout (Shopify has no webhook for it, so it checks every few minutes).';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'status' => Field::select('Status', ['open' => 'Still open', 'closed' => 'Completed'], null, 'open'),
                ...$this->windowFields(),
            ],
        ];
    }

    protected function path(): string
    {
        return '/checkouts.json';
    }

    protected function itemsKey(): string
    {
        return 'checkouts';
    }

    public function webhookTopic(): ?string
    {
        return null;
    }

    protected function filters(array $config): array
    {
        return [
            'status' => $config['status'] ?? 'open',
        ];
    }

    public function triggerName(): string
    {
        return 'Shopify: Abandoned checkout';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once for each checkout a customer left without paying, with the cart and the email to reach them.';
    }
}
