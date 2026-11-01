<?php

namespace App\Nodes\Integrations\Stripe;

use App\Models\Runs\Run;
use RuntimeException;

/**
 * "Checkout paid": the event trigger fixed to a Checkout session that has been
 * paid. A session that completes unpaid (a bank debit still clearing) is left
 * out until Stripe says the payment went through, so each paid session starts
 * one run. The run also gets what was bought (`line_items`), which the event
 * itself doesn't carry.
 */
class StripeCheckoutPaidNode extends StripeListEventsNode
{
    public function type(): string
    {
        return 'stripe_checkout_paid';
    }

    public function name(): string
    {
        return 'Stripe: Checkout Paid';
    }

    public function description(): string
    {
        return 'Lists Checkout sessions that were paid lately, newest first. As a trigger it starts the flow once for each paid session, instantly, with what was bought.';
    }

    public function configSchema(): array
    {
        $schema = parent::configSchema();
        unset($schema['properties']['types']);

        return $schema;
    }

    public function eventTypes(array $config): array
    {
        return ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];
    }

    protected function acceptsEvent(array $event): bool
    {
        return data_get($event, 'data.object.payment_status') === 'paid';
    }

    public function triggerName(): string
    {
        return 'Stripe: Checkout paid';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a customer pays a Checkout session, with the customer, the total and the line items.';
    }

    /**
     * The session next to the event, with what was bought. If Stripe can't be
     * asked for the items the run still starts, and says so.
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        $session = (array) ($item['object'] ?? []);
        $item['session'] = $session;
        $item['customer_email'] = data_get($session, 'customer_details.email') ?? ($session['customer_email'] ?? null);
        $item['line_items'] = [];

        if (! is_string($session['id'] ?? null) || preg_match('/^cs_[A-Za-z0-9_]+$/', $session['id']) !== 1) {
            return $item;
        }

        try {
            $items = $this->stripe($run, $config, 'GET', "/checkout/sessions/{$session['id']}/line_items", ['limit' => self::PAGE]);
            $item['line_items'] = array_values(array_filter((array) ($items['data'] ?? []), 'is_array'));
        } catch (RuntimeException $exception) {
            $item['line_items_error'] = $exception->getMessage();
        }

        return $item;
    }
}
