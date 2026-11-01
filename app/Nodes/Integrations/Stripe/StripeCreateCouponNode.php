<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class StripeCreateCouponNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_coupon';
    }

    public function name(): string
    {
        return 'Stripe: Create Coupon';
    }

    public function description(): string
    {
        return 'Creates a coupon for a percentage or a fixed amount off.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['duration'],
            'properties' => [
                ...$this->credentialFields(),
                'percent_off' => Field::text('Percent off', 'A number from 0.01 to 100. Use this or an amount.', '20'),
                'amount_off' => Field::integer('Amount off', 'In the smallest unit of the currency. Needs a currency.', null, 1),
                'currency' => Field::text('Currency', 'For an amount off.', 'usd'),
                'duration' => Field::select('Lasts', ['once' => 'Once', 'repeating' => 'For some months', 'forever' => 'Forever'], null, 'once'),
                'duration_in_months' => Field::integer('Months', 'For a coupon that lasts some months.', null, 1, 36),
                'name' => Field::text('Name', 'Shown to the customer.'),
                'max_redemptions' => Field::advanced(Field::integer('Most uses', null, null, 1)),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
                'idempotency_key' => $this->idempotencyField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $percent = filled($config['percent_off'] ?? null) ? (string) $config['percent_off'] : null;
        $amount = filled($config['amount_off'] ?? null) ? $this->amount($config, 'amount_off', 'The amount off') : null;

        if (($percent === null) === ($amount === null)) {
            throw new InvalidArgumentException('Give either a percent off or an amount off.');
        }

        if ($percent !== null && (! is_numeric($percent) || (float) $percent <= 0 || (float) $percent > 100)) {
            throw new InvalidArgumentException('The percent off is a number above 0 and up to 100.');
        }

        $duration = (string) ($config['duration'] ?? 'once');

        if (! in_array($duration, ['once', 'repeating', 'forever'], true)) {
            throw new InvalidArgumentException('A coupon lasts once, for some months, or forever.');
        }

        if ($duration === 'repeating' && blank($config['duration_in_months'] ?? null)) {
            throw new InvalidArgumentException('A coupon that lasts some months needs the number of months.');
        }

        return $this->stripe($run, $config, 'POST', '/coupons', [
            'percent_off' => $percent,
            'amount_off' => $amount,
            'currency' => $amount !== null ? $this->currency($config) : null,
            'duration' => $duration,
            'duration_in_months' => $duration === 'repeating' ? $this->amount($config, 'duration_in_months', 'The months') : null,
            ...$this->filled($config, ['name']),
            'max_redemptions' => filled($config['max_redemptions'] ?? null) ? $this->amount($config, 'max_redemptions', 'The most uses') : null,
            'metadata' => $this->metadata($config),
        ], $this->idempotencyKey($config));
    }
}
