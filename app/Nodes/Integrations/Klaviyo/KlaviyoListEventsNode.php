<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class KlaviyoListEventsNode extends AbstractKlaviyoNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'klaviyo_list_events';
    }

    public function name(): string
    {
        return 'Klaviyo: List Events';
    }

    public function description(): string
    {
        return 'Lists the events of one metric (Placed Order, Unsubscribed from Email Marketing, …), newest first. As a trigger it starts the flow for each new event.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['metric_id'],
            'properties' => [
                ...$this->credentialFields(),
                'metric_id' => Field::dynamic('Event kind', 'klaviyo.metrics', 'Pick a metric, e.g. Placed Order.', 'Placed Order'),
                'since' => Field::advanced(Field::datetime('Since', 'Only events at or after this time.')),
                'limit' => Field::integer('Max results', null, 50, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $metric = $this->resourceId($config, 'metric_id', 'The event kind');
        $filters = ['equals(metric_id,'.$this->quote($metric).')'];

        if (filled($config['since'] ?? null)) {
            $filters[] = 'greater-or-equal(datetime,'.Carbon::parse((string) $config['since'])->utc()->format('Y-m-d\\TH:i:s\\Z').')';
        }

        [$events, $included] = $this->listAll($run, $config, '/events', ['filter' => count($filters) === 1 ? $filters[0] : 'and('.implode(',', $filters).')', 'sort' => '-datetime', 'include' => 'metric'], $this->limit($config, 50, 10000), 200);
        $metricName = data_get(collect($included)->firstWhere('id', $metric), 'attributes.name');

        return ['events' => array_map(fn (array $event): array => [
            'id' => $event['id'] ?? null,
            'metric_id' => $metric,
            'metric' => $metricName,
            'occurred_at' => data_get($event, 'attributes.datetime'),
            'profile_id' => data_get($event, 'relationships.profile.data.id'),
            'value' => data_get($event, 'attributes.event_properties.$value'),
            'properties' => (object) (array) data_get($event, 'attributes.event_properties', []),
        ], $events), 'count' => count($events)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'events';
    }

    public function triggerName(): string
    {
        return 'Klaviyo: New event';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when Klaviyo records an event of the kind you pick (an order, a signup, an unsubscribe).';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'since' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
