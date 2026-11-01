<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookListEventsNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_list_events';
    }

    public function name(): string
    {
        return 'Outlook: List Events';
    }

    public function description(): string
    {
        return 'Lists calendar events between two times (the next 7 days by default).';
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
                'time_min' => Field::datetime('From', 'Defaults to now.'),
                'time_max' => Field::datetime('Until', 'Defaults to 7 days after From.'),
                'max_results' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $from = now()->parse((string) ($config['time_min'] ?? now()->toIso8601String()))->utc();
        $until = filled($config['time_max'] ?? null) ? now()->parse((string) $config['time_max'])->utc() : $from->copy()->addDays(7);

        return $this->get($run, '/me/calendarView', $config, [
            'startDateTime' => $from->toIso8601String(),
            'endDateTime' => $until->toIso8601String(),
            '$orderby' => 'start/dateTime',
            '$top' => min((int) ($config['max_results'] ?? 25), 100),
            '$select' => 'id,subject,bodyPreview,start,end,attendees,organizer,webLink,isCancelled,isOnlineMeeting,onlineMeeting,location',
        ]);
    }
}
