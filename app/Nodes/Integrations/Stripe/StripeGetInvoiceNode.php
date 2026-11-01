<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeGetInvoiceNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_get_invoice';
    }

    public function name(): string
    {
        return 'Stripe: Get Invoice';
    }

    public function description(): string
    {
        return 'Reads one invoice.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->stripe($run, $config, 'GET', '/invoices/'.$this->stripeId($config, 'invoice_id', 'in', 'invoice id'));
    }
}
