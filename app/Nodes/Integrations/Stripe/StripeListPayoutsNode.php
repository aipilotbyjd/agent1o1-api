<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeListPayoutsNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_list_payouts';
    }

    public function name(): string
    {
        return 'Stripe: List Payouts';
    }

    public function description(): string
    {
        return 'Lists payouts to the bank account, newest first.';
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
                'status' => Field::select('Status', ['' => 'Any', 'pending' => 'Pending', 'in_transit' => 'In transit', 'paid' => 'Paid', 'failed' => 'Failed', 'canceled' => 'Cancelled']),
                'since' => Field::advanced(Field::datetime('Created after', 'Only records created at or after this time.')),
                'until' => Field::advanced(Field::datetime('Created before', 'Only records created at or before this time.')),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOutput($run, $config, '/payouts', 'payouts', [
            'status' => filled($config['status'] ?? null) ? (string) $config['status'] : null,
            'created' => $this->createdRange($config),
        ]);
    }
}
