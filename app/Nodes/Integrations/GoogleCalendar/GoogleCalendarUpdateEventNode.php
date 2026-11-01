<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleCalendarUpdateEventNode extends AbstractGoogleCalendarNode
{
    public function type(): string
    {
        return 'google_calendar_update_event';
    }

    public function name(): string
    {
        return 'Google Calendar: Update Event';
    }

    public function description(): string
    {
        return 'Changes an event. Fields you leave empty stay as they are.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
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
                'summary' => Field::text('Title', 'Leave empty to keep it.'),
                'description' => Field::textarea('Description', 'Leave empty to keep it.'),
                'location' => Field::text('Location', 'Leave empty to keep it.'),
                'start_at' => Field::datetime('Starts at', 'Leave empty to keep the time. Set both start and end to move the event.'),
                'end_at' => Field::datetime('Ends at'),
                'all_day' => Field::boolean('All-day event', 'Use only the dates of the start and end.'),
                'attendees' => Field::emails('Guests', 'Replaces the guest list. Leave empty to keep it.'),
                'time_zone' => Field::advanced(Field::text('Time zone', 'Used when the times above have no offset.', 'Asia/Kolkata')),
                'send_updates' => Field::advanced(Field::select('Email guests', [
                    'all' => 'Everyone',
                    'externalOnly' => 'Guests outside your organisation',
                    'none' => 'Nobody',
                ], null, 'all')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->eventBody($config);

        if ($body === []) {
            throw new InvalidArgumentException('Google Calendar: choose at least one field to change.');
        }

        return $this->patch(
            $run,
            $this->eventsPath($config, (string) $config['event_id']).'?'.http_build_query(['sendUpdates' => (string) ($config['send_updates'] ?? 'all')]),
            $config,
            $body,
        );
    }
}
