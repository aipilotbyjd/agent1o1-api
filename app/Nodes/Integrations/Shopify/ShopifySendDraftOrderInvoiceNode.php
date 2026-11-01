<?php

namespace App\Nodes\Integrations\Shopify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ShopifySendDraftOrderInvoiceNode extends AbstractShopifyNode
{
    public function type(): string
    {
        return 'shopify_send_draft_order_invoice';
    }

    public function name(): string
    {
        return 'Shopify: Send Draft Order Invoice';
    }

    public function description(): string
    {
        return 'Emails the customer a link to pay a draft order.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['draft_order_id'],
            'properties' => [
                ...$this->credentialFields(),
                'draft_order_id' => Field::text('Draft order ID', null, '994118539'),
                'to' => Field::text('To', 'Leave empty to use the draft\'s email.'),
                'subject' => Field::text('Subject'),
                'custom_message' => Field::textarea('Message'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->shopifyId($config, 'draft_order_id', 'draft order id');
        $invoice = $this->shopify($run, $config, 'POST', "/draft_orders/{$id}/send_invoice.json", ['draft_order_invoice' => $this->filled($config, ['to', 'subject', 'custom_message'])]);

        return (array) ($invoice['draft_order_invoice'] ?? []);
    }
}
