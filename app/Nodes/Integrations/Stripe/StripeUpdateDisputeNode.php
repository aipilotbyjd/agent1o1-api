<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class StripeUpdateDisputeNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_update_dispute';
    }

    public function name(): string
    {
        return 'Stripe: Update Dispute';
    }

    public function description(): string
    {
        return 'Adds evidence to a dispute, and sends it to the bank when you say so. Once sent it can\'t be changed.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['dispute_id'],
            'properties' => [
                ...$this->credentialFields(),
                'dispute_id' => Field::text('Dispute ID', null, 'dp_...'),
                'evidence' => Field::json('Evidence', 'Stripe\'s evidence fields as JSON, such as {"customer_name": "Ada", "product_description": "Pro plan", "uncategorized_text": "..."}.'),
                'submit' => Field::boolean('Send to the bank now', 'Submits the evidence. It can\'t be changed afterwards.'),
                'metadata' => Field::advanced(Field::keyValue('Metadata', 'Your own notes on the record, such as an order id.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $evidence = $this->decoded($config['evidence'] ?? null, 'The evidence') ?? [];
        $evidence = collect($evidence)->reject(fn (mixed $value): bool => is_array($value) || $value === null)->map(fn (mixed $value): string => (string) $value)->all();

        return $this->stripe($run, $config, 'POST', '/disputes/'.$this->stripeId($config, 'dispute_id', 'dp', 'dispute id'), [
            'evidence' => $evidence !== [] ? $evidence : null,
            'submit' => filter_var($config['submit'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'metadata' => $this->metadata($config),
        ]);
    }
}
