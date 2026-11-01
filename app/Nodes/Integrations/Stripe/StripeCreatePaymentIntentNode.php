<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class StripeCreatePaymentIntentNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_payment_intent';
    }

    public function name(): string
    {
        return 'Stripe: Create Payment Intent';
    }

    public function description(): string
    {
        return 'Starts a payment. With "Confirm now" and a saved payment method it charges the customer straight away.';
    }

    public function effect(array $config): ActionEffect
    {
        return filter_var($config['confirm'] ?? false, FILTER_VALIDATE_BOOLEAN) ? ActionEffect::External : ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['amount', 'currency'],
            'properties' => [
                ...$this->credentialFields(),
                'amount' => Field::integer('Amount', 'In the smallest unit of the currency: 1999 is 19.99 USD.', null, 1),
                'currency' => Field::text('Currency', null, 'usd'),
                'customer_id' => Field::dynamic('Customer', 'stripe.customers', null, 'cus_...'),
                'payment_method' => Field::text('Payment method', 'The saved payment method to use, such as pm_...', 'pm_...'),
                'confirm' => Field::boolean('Confirm now', 'Charge the payment method straight away. It needs a payment method.'),
                'description' => Field::advanced(Field::text('Description')),
                'receipt_email' => Field::advanced(Field::text('Receipt email')),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $confirm = filter_var($config['confirm'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($confirm && blank($config['payment_method'] ?? null)) {
            throw new InvalidArgumentException('Confirming a payment needs a payment method.');
        }

        return $this->stripe($run, $config, 'POST', '/payment_intents', [
            'amount' => $this->amount($config, 'amount', 'The amount'),
            'currency' => $this->currency($config),
            'customer' => $this->optionalId($config, 'customer_id', 'cus', 'customer id'),
            'payment_method' => $this->optionalId($config, 'payment_method', 'pm', 'payment method id'),
            'confirm' => $confirm ?: null,
            'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => $confirm ? 'never' : null],
            ...$this->filled($config, ['description', 'receipt_email']),
            'metadata' => $this->metadata($config),
        ], $this->idempotencyKey($config));
    }
}
