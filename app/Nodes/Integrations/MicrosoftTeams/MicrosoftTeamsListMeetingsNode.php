<?php

namespace App\Nodes\Integrations\MicrosoftTeams;

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class MicrosoftTeamsListMeetingsNode extends AbstractMicrosoftTeamsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'microsoft_teams_list_meetings';
    }

    public function name(): string
    {
        return 'Microsoft Teams: List Meetings';
    }

    public function description(): string
    {
        return 'Lists Teams meetings on your calendar over the coming days. As a trigger it starts the flow for each new one.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'days' => Field::integer('Look ahead (days)', null, 7, 1, 60),
                'limit' => Field::integer('Most meetings', null, 50, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $now = now()->utc();

        $body = $this->call($run, $config, 'GET', self::API_URL.'/me/calendarView', query: [
            'startDateTime' => $now->format('Y-m-d\TH:i:s\Z'),
            'endDateTime' => $now->copy()->addDays(max(1, min(60, (int) ($config['days'] ?? 7))))->format('Y-m-d\TH:i:s\Z'),
            '$top' => 200,
            '$orderby' => 'start/dateTime',
            '$select' => 'id,subject,start,end,organizer,attendees,onlineMeeting,isOnlineMeeting,onlineMeetingProvider,isCancelled,webLink',
        ]);

        $meetings = collect((array) ($body['value'] ?? []))
            ->filter(fn (mixed $event): bool => (bool) ($event['isOnlineMeeting'] ?? false) && ($event['onlineMeetingProvider'] ?? '') === 'teamsForBusiness' && ! ($event['isCancelled'] ?? false))
            ->take(max(1, min(200, (int) ($config['limit'] ?? 50))))
            ->map(fn (mixed $event): array => $this->shapeMeeting((array) $event))
            ->values()
            ->all();

        return ['meetings' => $meetings, 'count' => count($meetings)];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'meetings' => ['title' => 'Meetings', 'description' => 'Each: meeting_id, subject, start_time, end_time, join_url, organizer and attendees.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'meetings';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['meeting_id'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Microsoft Teams: New meeting';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Teams meeting is added to your calendar, so you can prepare a brief or tell your team.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'days' => 30, 'limit' => 200];
    }
}
