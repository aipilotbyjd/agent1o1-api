<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeSendInvoiceNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_send_invoice';
    }

    public function name(): string
    {
        return 'Stripe: Send Invoice';
    }

    public function description(): string
    {
        return 'Emails an open invoice to the customer.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['invoice_id'],
            'properties' => [
                ...$this->credentialFields(),
                'invoice_id' => Field::text('Invoice ID', null, 'in_...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->stripe($run, $config, 'POST', '/invoices/'.$this->stripeId($config, 'invoice_id', 'in', 'invoice id').'/send');
    }
}
