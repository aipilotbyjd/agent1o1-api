<?php

namespace App\Nodes\Integrations\MicrosoftTeams;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MicrosoftTeamsGetMeetingNode extends AbstractMicrosoftTeamsNode
{
    public function type(): string
    {
        return 'microsoft_teams_get_meeting';
    }

    public function name(): string
    {
        return 'Microsoft Teams: Get Meeting';
    }

    public function description(): string
    {
        return 'Gets a Teams meeting’s details: when it is, its join link and who has accepted.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['meeting_id'],
            'properties' => [
                ...$this->credentialFields(),
                'meeting_id' => Field::text('Meeting ID', 'From Create Meeting or List Meetings.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeMeeting($this->call($run, $config, 'GET', self::API_URL.'/me/events/'.$this->eventId($config)));
    }
}
