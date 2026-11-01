<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class StripeCreatePayoutNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_payout';
    }

    public function name(): string
    {
        return 'Stripe: Create Payout';
    }

    public function description(): string
    {
        return 'Pays out part of the available balance to the bank account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['amount', 'currency'],
            'properties' => [
                ...$this->credentialFields(),
                'amount' => Field::integer('Amount', 'In the smallest unit of the currency.', null, 1),
                'currency' => Field::text('Currency', null, 'usd'),
                'method' => Field::select('Speed', ['standard' => 'Standard', 'instant' => 'Instant (fee)'], null, 'standard'),
                'description' => Field::advanced(Field::text('Description')),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $method = (string) ($config['method'] ?? 'standard');

        if (! in_array($method, ['standard', 'instant'], true)) {
            throw new InvalidArgumentException('The speed is standard or instant.');
        }

        return $this->stripe($run, $config, 'POST', '/payouts', [
            'amount' => $this->amount($config, 'amount', 'The amount'),
            'currency' => $this->currency($config),
            'method' => $method,
            ...$this->filled($config, ['description']),
            'metadata' => $this->metadata($config),
        ], $this->idempotencyKey($config));
    }
}
