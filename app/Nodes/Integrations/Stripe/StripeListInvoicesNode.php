<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeListInvoicesNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_list_invoices';
    }

    public function name(): string
    {
        return 'Stripe: List Invoices';
    }

    public function description(): string
    {
        return 'Lists invoices, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'customer_id' => Field::dynamic('Customer', 'stripe.customers', null, 'cus_...'),
                'subscription_id' => Field::advanced(Field::dynamic('Subscription', 'stripe.subscriptions', null, 'sub_...')),
                'status' => Field::select('Status', ['' => 'Any', 'draft' => 'Draft', 'open' => 'Open', 'paid' => 'Paid', 'uncollectible' => 'Uncollectible', 'void' => 'Void']),
                'since' => Field::advanced(Field::datetime('Created after', 'Only records created at or after this time.')),
                'until' => Field::advanced(Field::datetime('Created before', 'Only records created at or before this time.')),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOutput($run, $config, '/invoices', 'invoices', [
            'customer' => $this->optionalId($config, 'customer_id', 'cus', 'customer id'),
            'subscription' => $this->optionalId($config, 'subscription_id', 'sub', 'subscription id'),
            'status' => filled($config['status'] ?? null) ? (string) $config['status'] : null,
            'created' => $this->createdRange($config),
        ]);
    }
}
