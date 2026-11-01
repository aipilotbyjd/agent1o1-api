<?php

namespace App\Nodes\Integrations\Zoom;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZoomListMeetingsNode extends AbstractZoomNode
{
    public function type(): string
    {
        return 'zoom_list_meetings';
    }

    public function name(): string
    {
        return 'Zoom: List Meetings';
    }

    public function description(): string
    {
        return 'Lists your scheduled, upcoming or live meetings.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'which' => Field::select('Show', ['upcoming' => 'Upcoming', 'scheduled' => 'All scheduled', 'live' => 'Happening now'], null, 'upcoming'),
                'limit' => Field::integer('Most meetings', null, 30, 1, 300),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $which = in_array($config['which'] ?? 'upcoming', ['upcoming', 'scheduled', 'live'], true) ? $config['which'] ?? 'upcoming' : 'upcoming';

        $body = $this->call($run, $config, 'GET', self::API_URL.'/users/me/meetings', query: ['type' => $which, 'page_size' => max(1, min(300, (int) ($config['limit'] ?? 30)))]);
        $meetings = array_map(fn (mixed $meeting): array => $this->shapeMeeting((array) $meeting), (array) ($body['meetings'] ?? []));

        return ['meetings' => $meetings, 'count' => count($meetings)];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'meetings' => ['title' => 'Meetings', 'description' => 'Each: meeting_id, topic, start_time, duration and join_url.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }
}
