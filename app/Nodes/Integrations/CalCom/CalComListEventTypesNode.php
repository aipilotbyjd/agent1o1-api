<?php

namespace App\Nodes\Integrations\CalCom;

use App\Models\Runs\Run;

class CalComListEventTypesNode extends AbstractCalComNode
{
    public function type(): string
    {
        return 'cal_com_list_event_types';
    }

    public function name(): string
    {
        return 'Cal.com: List Event Types';
    }

    public function description(): string
    {
        return 'Lists the kinds of meeting people can book with you, with their numbers and lengths.';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'required' => [], 'properties' => [...$this->credentialFields()]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->call($run, $config, 'GET', self::API_URL.'/event-types', headers: $this->version(self::EVENT_TYPES_VERSION));

        $types = array_map(fn (mixed $type): array => [
            'event_type_id' => $type['id'] ?? null,
            'title' => $type['title'] ?? null,
            'slug' => $type['slug'] ?? null,
            'duration' => $type['lengthInMinutes'] ?? $type['length'] ?? null,
            'description' => $type['description'] ?? null,
            'hidden' => (bool) ($type['hidden'] ?? false),
        ], (array) ($body['data'] ?? []));

        return ['event_types' => $types, 'count' => count($types)];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'event_types' => ['title' => 'Meeting types', 'description' => 'Each: event_type_id, title, slug, duration, description and hidden.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }
}
