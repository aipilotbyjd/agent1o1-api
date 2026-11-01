<?php

namespace App\Nodes\Integrations\Zoom;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZoomGetMeetingNode extends AbstractZoomNode
{
    public function type(): string
    {
        return 'zoom_get_meeting';
    }

    public function name(): string
    {
        return 'Zoom: Get Meeting';
    }

    public function description(): string
    {
        return 'Gets a meeting’s details: topic, time, length and its join link.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['meeting_id'],
            'properties' => [
                ...$this->credentialFields(),
                'meeting_id' => Field::text('Meeting ID', 'From Create Meeting or List Meetings.', '85012345678'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeMeeting($this->call($run, $config, 'GET', self::API_URL.'/meetings/'.$this->meetingId($config)));
    }
}
