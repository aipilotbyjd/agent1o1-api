<?php

namespace App\Nodes\Integrations\Calendly;

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Carbon\CarbonImmutable;

class CalendlyListScheduledEventsNode extends AbstractCalendlyNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'calendly_list_scheduled_events';
    }

    public function name(): string
    {
        return 'Calendly: List Scheduled Events';
    }

    public function description(): string
    {
        return 'Lists the meetings people have booked with you, by date and status. As a trigger it starts the flow for each new booking.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'status' => Field::select('Status', ['active' => 'Booked', 'canceled' => 'Cancelled'], null, 'active'),
                'from' => Field::datetime('From', 'Meetings starting at or after this. Leave empty for now.'),
                'until' => Field::datetime('Until', 'Meetings starting before this. Leave empty for no limit.'),
                'limit' => Field::integer('Most meetings', null, 50, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $me = $this->me($run, $config);

        $body = $this->call($run, $config, 'GET', self::API_URL.'/scheduled_events', query: [
            'user' => $me['uri'],
            'status' => ($config['status'] ?? 'active') === 'canceled' ? 'canceled' : 'active',
            'min_start_time' => filled($config['from'] ?? null) ? $this->iso($config['from']) : now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'max_start_time' => filled($config['until'] ?? null) ? $this->iso($config['until']) : null,
            'count' => max(1, min(100, (int) ($config['limit'] ?? 50))),
            'sort' => 'start_time:asc',
        ]);

        $events = array_map(fn (mixed $event): array => $this->shapeEvent((array) $event), (array) ($body['collection'] ?? []));

        return ['events' => $events, 'count' => count($events)];
    }

    private function iso(mixed $value): string
    {
        return $this->utcFromAny($value)->format('Y-m-d\TH:i:s\Z');
    }

    private function utcFromAny(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $value)->utc();
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'events' => ['title' => 'Meetings', 'description' => 'Each: event_id, name, status, start_time, end_time, join_url, invitees and hosts.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'events';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['event_id'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Calendly: New booking';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone books a meeting with you, so you can prepare, notify the team or add them to your CRM.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'status' => 'active', 'from' => now()->toIso8601String(), 'until' => null, 'limit' => 100];
    }

    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
