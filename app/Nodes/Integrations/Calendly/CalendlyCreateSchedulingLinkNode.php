<?php

namespace App\Nodes\Integrations\Calendly;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class CalendlyCreateSchedulingLinkNode extends AbstractCalendlyNode
{
    public function type(): string
    {
        return 'calendly_create_scheduling_link';
    }

    public function name(): string
    {
        return 'Calendly: Create Scheduling Link';
    }

    public function description(): string
    {
        return 'Creates a booking link for one meeting type that works once, to send a lead who should book a single time with you.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_type'],
            'properties' => [
                ...$this->credentialFields(),
                'event_type' => Field::text('Meeting type', 'The meeting type’s ID or address, from List Event Types.', 'a1b2c3d4-0000-0000-0000-000000000000'),
                'uses' => Field::integer('Times it can be used', 'After this many bookings the link stops working.', 1, 1, 1),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->idFrom($config, 'event_type', 'a meeting type');

        $link = (array) ($this->call($run, $config, 'POST', self::API_URL.'/scheduling_links', [
            'max_event_count' => 1,
            'owner' => self::API_URL."/event_types/{$id}",
            'owner_type' => 'EventType',
        ])['resource'] ?? []);

        return ['booking_url' => $link['booking_url'] ?? null, 'event_type_id' => $id];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'booking_url' => ['title' => 'Booking link', 'description' => 'A one-time link to send the person.'],
                'event_type_id' => ['title' => 'Meeting type', 'description' => 'The meeting type it books.'],
            ],
        ];
    }
}
