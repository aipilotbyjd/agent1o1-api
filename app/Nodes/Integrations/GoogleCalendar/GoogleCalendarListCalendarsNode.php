<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleCalendarListCalendarsNode extends AbstractGoogleCalendarNode
{
    public function type(): string
    {
        return 'google_calendar_list_calendars';
    }

    public function name(): string
    {
        return 'Google Calendar: List Calendars';
    }

    public function description(): string
    {
        return 'Lists the calendars the account can see.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'max_results' => Field::integer('Max results', null, 50, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, '/users/me/calendarList', $config, ['maxResults' => $config['max_results'] ?? 50]);

        return [
            'calendars' => array_map(fn (array $calendar): array => [
                'id' => $calendar['id'] ?? null,
                'name' => $calendar['summaryOverride'] ?? $calendar['summary'] ?? null,
                'primary' => (bool) ($calendar['primary'] ?? false),
                'time_zone' => $calendar['timeZone'] ?? null,
                'access_role' => $calendar['accessRole'] ?? null,
            ], (array) ($response['items'] ?? [])),
        ];
    }
}
