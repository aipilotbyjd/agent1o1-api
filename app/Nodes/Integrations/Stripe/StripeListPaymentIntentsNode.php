<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeListPaymentIntentsNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_list_payment_intents';
    }

    public function name(): string
    {
        return 'Stripe: List Payment Intents';
    }

    public function description(): string
    {
        return 'Lists payments, newest first.';
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
                'customer_id' => Field::dynamic('Customer', 'stripe.customers', 'Only this customer\'s payments.', 'cus_...'),
                'since' => Field::advanced(Field::datetime('Created after', 'Only records created at or after this time.')),
                'until' => Field::advanced(Field::datetime('Created before', 'Only records created at or before this time.')),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOutput($run, $config, '/payment_intents', 'payment_intents', [
            'customer' => $this->optionalId($config, 'customer_id', 'cus', 'customer id'),
            'created' => $this->createdRange($config),
        ]);
    }
}
