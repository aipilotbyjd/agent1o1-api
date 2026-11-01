<?php

namespace App\Nodes\Integrations\Calendly;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class CalendlyGetEventNode extends AbstractCalendlyNode
{
    public function type(): string
    {
        return 'calendly_get_event';
    }

    public function name(): string
    {
        return 'Calendly: Get Event';
    }

    public function description(): string
    {
        return 'Gets one booked meeting’s details: when, where, who is hosting and whether it was cancelled.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event'],
            'properties' => [
                ...$this->credentialFields(),
                'event' => Field::text('Meeting', 'The event ID, or its whole address, from List Scheduled Events.', 'a1b2c3d4-0000-0000-0000-000000000000'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->idFrom($config, 'event', 'a booked meeting');

        return $this->shapeEvent((array) ($this->call($run, $config, 'GET', self::API_URL."/scheduled_events/{$id}")['resource'] ?? []));
    }
}
