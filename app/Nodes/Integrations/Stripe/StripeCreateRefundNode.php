<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class StripeCreateRefundNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_refund';
    }

    public function name(): string
    {
        return 'Stripe: Create Refund';
    }

    public function description(): string
    {
        return 'Refunds a payment, in full or in part. Name the payment intent or the charge.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'payment_intent_id' => Field::text('Payment intent ID', 'The payment to refund. Use this or a charge.', 'pi_...'),
                'charge_id' => Field::text('Charge ID', null, 'ch_...'),
                'amount' => Field::integer('Amount', 'In the smallest unit of the currency. Empty refunds everything that is left.', null, 1),
                'reason' => Field::select('Reason', ['duplicate' => 'Duplicate', 'fraudulent' => 'Fraudulent', 'requested_by_customer' => 'Requested by customer']),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $intent = $this->optionalId($config, 'payment_intent_id', 'pi', 'payment intent id');
        $charge = $this->optionalId($config, 'charge_id', 'ch', 'charge id');

        if (($intent === null) === ($charge === null)) {
            throw new InvalidArgumentException('Name either the payment intent or the charge to refund.');
        }

        return $this->stripe($run, $config, 'POST', '/refunds', [
            'payment_intent' => $intent,
            'charge' => $charge,
            'amount' => filled($config['amount'] ?? null) ? $this->amount($config, 'amount', 'The amount') : null,
            'reason' => filled($config['reason'] ?? null) ? (string) $config['reason'] : null,
            'metadata' => $this->metadata($config),
        ], $this->idempotencyKey($config));
    }
}
