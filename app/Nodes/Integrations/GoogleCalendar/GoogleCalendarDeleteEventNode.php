<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleCalendarDeleteEventNode extends AbstractGoogleCalendarNode
{
    public function type(): string
    {
        return 'google_calendar_delete_event';
    }

    public function name(): string
    {
        return 'Google Calendar: Delete Event';
    }

    public function description(): string
    {
        return 'Deletes an event from a calendar.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
                'send_updates' => Field::advanced(Field::select('Tell guests', [
                    'all' => 'Everyone',
                    'externalOnly' => 'Guests outside your organisation',
                    'none' => 'Nobody',
                ], null, 'none')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $calendarId = $config['calendar_id'] ?? 'primary';

        return $this->delete(
            $run,
            '/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($config['event_id']),
            $config,
            ['sendUpdates' => (string) ($config['send_updates'] ?? 'none')],
        );
    }
}
