<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleCalendarCheckAvailabilityNode extends AbstractGoogleCalendarNode
{
    public function type(): string
    {
        return 'google_calendar_check_availability';
    }

    public function name(): string
    {
        return 'Google Calendar: Check Availability';
    }

    public function description(): string
    {
        return 'Returns the busy time slots of one or more calendars in a time window.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['time_min', 'time_max'],
            'properties' => [
                ...$this->credentialFields(),
                'time_min' => Field::datetime('From'),
                'time_max' => Field::datetime('Until'),
                'calendar_ids' => Field::dynamic('Calendars', 'google_calendar.calendars', 'Defaults to your primary calendar. Other people\'s calendars work if they share free/busy with you.', 'primary', multiple: true, type: 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $ids = array_values(array_filter((array) ($config['calendar_ids'] ?? []))) ?: ['primary'];

        $response = $this->post($run, '/freeBusy', $config, [
            'timeMin' => $config['time_min'],
            'timeMax' => $config['time_max'],
            'items' => array_map(fn (string $id): array => ['id' => $id], $ids),
        ]);

        $calendars = [];

        foreach ((array) ($response['calendars'] ?? []) as $id => $calendar) {
            $calendars[$id] = [
                'busy' => array_values((array) ($calendar['busy'] ?? [])),
                'errors' => array_values((array) ($calendar['errors'] ?? [])),
            ];
        }

        return [
            'calendars' => $calendars,
            'is_free' => collect($calendars)->every(fn (array $calendar): bool => $calendar['busy'] === [] && $calendar['errors'] === []),
        ];
    }
}
