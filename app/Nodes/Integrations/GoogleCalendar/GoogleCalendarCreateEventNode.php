<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Str;

class GoogleCalendarCreateEventNode extends AbstractGoogleCalendarNode
{
    public function type(): string
    {
        return 'google_calendar_create_event';
    }

    public function name(): string
    {
        return 'Google Calendar: Create Event';
    }

    public function description(): string
    {
        return 'Creates an event, optionally with guests and a Google Meet link.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['summary', 'start_at', 'end_at'],
            'properties' => [
                ...$this->credentialFields(),
                'calendar_id' => Field::dynamic('Calendar', 'google_calendar.calendars', 'Defaults to your primary calendar.', 'primary'),
                'summary' => Field::text('Title', null, 'Team sync'),
                'description' => Field::textarea('Description'),
                'location' => Field::text('Location', null, 'Room 4 or an address'),
                'start_at' => Field::datetime('Starts at'),
                'end_at' => Field::datetime('Ends at', 'For an all-day event, the day after its last day.'),
                'all_day' => Field::boolean('All-day event', 'Use only the dates of the start and end.'),
                'attendees' => Field::emails('Guests', 'They are invited by email.'),
                'add_meet' => Field::boolean('Add a Google Meet link'),
                'time_zone' => Field::advanced(Field::text('Time zone', 'Used when the times above have no offset. Defaults to the calendar\'s zone.', 'Asia/Kolkata')),
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
        $addMeet = (bool) ($config['add_meet'] ?? false);

        if ($addMeet) {
            $body['conferenceData'] = ['createRequest' => [
                'requestId' => (string) Str::uuid(),
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ]];
        }

        $query = http_build_query([
            'sendUpdates' => (string) ($config['send_updates'] ?? 'all'),
            ...($addMeet ? ['conferenceDataVersion' => 1] : []),
        ]);

        return $this->post($run, $this->eventsPath($config)."?{$query}", $config, $body);
    }
}
