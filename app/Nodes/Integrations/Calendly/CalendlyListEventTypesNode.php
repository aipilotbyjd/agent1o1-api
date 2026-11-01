<?php

namespace App\Nodes\Integrations\Calendly;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class CalendlyListEventTypesNode extends AbstractCalendlyNode
{
    public function type(): string
    {
        return 'calendly_list_event_types';
    }

    public function name(): string
    {
        return 'Calendly: List Event Types';
    }

    public function description(): string
    {
        return 'Lists the kinds of meeting people can book with you, with their length and booking page.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'active_only' => Field::boolean('Only those open for booking', null, true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->call($run, $config, 'GET', self::API_URL.'/event_types', query: [
            'user' => $this->me($run, $config)['uri'],
            'active' => (bool) ($config['active_only'] ?? true) ? 'true' : null,
            'count' => 100,
        ]);

        $types = array_map(fn (mixed $type): array => [
            'event_type_id' => basename((string) ($type['uri'] ?? '')) ?: null,
            'uri' => $type['uri'] ?? null,
            'name' => $type['name'] ?? null,
            'duration' => $type['duration'] ?? null,
            'active' => (bool) ($type['active'] ?? false),
            'booking_url' => $type['scheduling_url'] ?? null,
            'description' => $type['description_plain'] ?? null,
        ], (array) ($body['collection'] ?? []));

        return ['event_types' => $types, 'count' => count($types)];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'event_types' => ['title' => 'Meeting types', 'description' => 'Each: event_type_id, uri, name, duration, active, booking_url and description.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }
}
