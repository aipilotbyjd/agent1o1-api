<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleCalendarGetEventNode extends AbstractGoogleCalendarNode
{
    public function type(): string
    {
        return 'google_calendar_get_event';
    }

    public function name(): string
    {
        return 'Google Calendar: Get Event';
    }

    public function description(): string
    {
        return 'Reads one event: its time, guests, location and Meet link.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_id'],
            'properties' => [
                ...$this->credentialFields(),
                'calendar_id' => Field::dynamic('Calendar', 'google_calendar.calendars', 'Defaults to your primary calendar.', 'primary'),
                'event_id' => Field::dynamic('Event', 'google_calendar.events', 'Pick an upcoming event, or map an event ID.', uses: ['calendar_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, $this->eventsPath($config, (string) $config['event_id']), $config);
    }
}
