<?php

namespace App\Nodes\Integrations\Stripe;

use App\Models\Runs\Run;

/**
 * "Dispute opened": the event trigger with its event fixed, so there is nothing
 * to pick. A customer disputing a charge starts one run, with the dispute next
 * to the event (`dispute.amount`, `dispute.reason`, `dispute.evidence_details.due_by`).
 */
class StripeDisputeOpenedNode extends StripeListEventsNode
{
    public function type(): string
    {
        return 'stripe_dispute_opened';
    }

    public function name(): string
    {
        return 'Stripe: Dispute Opened';
    }

    public function description(): string
    {
        return 'Lists the disputes customers opened lately, newest first. As a trigger it starts the flow once for each dispute, instantly.';
    }

    public function configSchema(): array
    {
        $schema = parent::configSchema();
        unset($schema['properties']['types']);

        return $schema;
    }

    public function eventTypes(array $config): array
    {
        return ['charge.dispute.created'];
    }

    public function triggerName(): string
    {
        return 'Stripe: Dispute opened';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a customer disputes a charge, with the amount, the reason and the date the evidence is due.';
    }

    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        return [...$item, 'dispute' => $item['object'] ?? []];
    }
}
