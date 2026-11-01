<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleCalendarQuickAddEventNode extends AbstractGoogleCalendarNode
{
    public function type(): string
    {
        return 'google_calendar_quick_add_event';
    }

    public function name(): string
    {
        return 'Google Calendar: Quick Add Event';
    }

    public function description(): string
    {
        return 'Creates an event from a sentence, such as "Lunch with Ada tomorrow at noon".';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['text'],
            'properties' => [
                ...$this->credentialFields(),
                'calendar_id' => Field::dynamic('Calendar', 'google_calendar.calendars', 'Defaults to your primary calendar.', 'primary'),
                'text' => Field::textarea('Event text', 'Google works out the title, date and time from it.', 'Dentist next Tuesday 3pm'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, $this->eventsPath($config).'/quickAdd?'.http_build_query(['text' => (string) $config['text']]), $config);
    }
}
