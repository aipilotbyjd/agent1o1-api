<?php

namespace App\Nodes\Integrations\Stripe;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Str;

class StripeListEventsNode extends AbstractStripeNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a poll looks for events made since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * Event names Stripe filters on its side in one call.
     */
    private const int SERVER_FILTER_LIMIT = 20;

    public function type(): string
    {
        return 'stripe_list_events';
    }

    public function name(): string
    {
        return 'Stripe: List Events';
    }

    public function description(): string
    {
        return 'Lists what happened on the account (a payment succeeded, an invoice was paid, a subscription ended), newest first. Stripe keeps events for 30 days. As a trigger it starts the flow once for each new event, instantly.';
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
                'types' => Field::list('Event types', 'Leave empty for every event. Names such as payment_intent.succeeded or invoice.paid; customer.* takes a whole group.', 'payment_intent.succeeded'),
                'since' => Field::advanced(Field::datetime('Created after', 'Only events at or after this time.')),
                'until' => Field::advanced(Field::datetime('Created before', 'Only events at or before this time.')),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $types = $this->eventTypes($config);
        $result = $this->listAll($run, $config, '/events', [
            'type' => count($types) === 1 ? $types[0] : null,
            'types' => count($types) > 1 && count($types) <= self::SERVER_FILTER_LIMIT && ! Str::contains(implode('', $types), '*') ? $types : null,
            'created' => $this->createdRange($config),
        ], $this->limit($config));

        // Stripe filters on its side when it can; this makes the answer the same when it can't.
        $events = array_values(array_filter($result['items'], fn (array $event): bool => ($types === [] || Str::is($types, (string) ($event['type'] ?? ''))) && $this->acceptsEvent($event)));

        return ['events' => array_map(fn (array $event): array => $this->shapeEvent($event), $events), 'count' => count($events), 'has_more' => $result['has_more']];
    }

    /**
     * Whether an event of the right type is one the node is about (a trigger
     * preset narrows it further).
     *
     * @param  array<string, mixed>  $event
     */
    protected function acceptsEvent(array $event): bool
    {
        return true;
    }

    /**
     * The event names the step is about, as typed.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function eventTypes(array $config): array
    {
        $types = $config['types'] ?? [];

        if (is_string($types)) {
            $types = preg_split('/[\s,]+/', $types) ?: [];
        }

        return array_values(array_unique(array_filter(array_map(fn (mixed $type): string => trim((string) $type), (array) $types), fn (string $type): bool => preg_match('/^[a-z0-9_.*]+$/', $type) === 1 && $type !== '*')));
    }

    protected function triggerItemsPath(): ?string
    {
        return 'events';
    }

    /**
     * An event's id: it never changes and is unique to the event, so a run
     * starts once for it however many times Stripe delivers it or a poll sees it.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Stripe: New event';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once for each Stripe event you pick (a payment, an invoice, a subscription change), with the object it is about.';
    }

    /**
     * Events of the last few hours, with a wide page.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'since' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'until' => null, 'limit' => 500];
    }

    /**
     * A safety net: a trigger is also pushed.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
