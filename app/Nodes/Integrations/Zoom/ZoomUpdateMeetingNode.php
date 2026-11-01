<?php

namespace App\Nodes\Integrations\Zoom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZoomUpdateMeetingNode extends AbstractZoomNode
{
    public function type(): string
    {
        return 'zoom_update_meeting';
    }

    public function name(): string
    {
        return 'Zoom: Update Meeting';
    }

    public function description(): string
    {
        return 'Changes a meeting’s topic, time, length or agenda. Only what you fill in changes; the join link stays the same.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['meeting_id'],
            'properties' => [
                ...$this->credentialFields(),
                'meeting_id' => Field::text('Meeting ID', null, '85012345678'),
                'topic' => Field::text('New topic'),
                'start_at' => Field::datetime('New start time'),
                'timezone' => Field::text('Timezone', 'How to read the new start time. Defaults to UTC.', 'Europe/London'),
                'duration' => Field::integer('New length (minutes)', null, null, 1, 1440),
                'agenda' => Field::textarea('New agenda'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->meetingId($config);

        $changes = array_filter([
            'topic' => filled($config['topic'] ?? null) ? mb_substr(trim((string) $config['topic']), 0, 200) : null,
            'start_time' => filled($config['start_at'] ?? null) ? $this->utc($config['start_at'], $config['timezone'] ?? null, 'The new start time')->format('Y-m-d\TH:i:s\Z') : null,
            'timezone' => filled($config['start_at'] ?? null) ? 'UTC' : null,
            'duration' => filled($config['duration'] ?? null) ? $this->minutes($config['duration']) : null,
            'agenda' => filled($config['agenda'] ?? null) ? mb_substr(trim((string) $config['agenda']), 0, 2000) : null,
        ], fn (mixed $value): bool => $value !== null);

        if ($changes === []) {
            throw new InvalidArgumentException('Fill in at least one thing to change.');
        }

        $this->call($run, $config, 'PATCH', self::API_URL."/meetings/{$id}", $changes);

        // Zoom answers an update with no body, so read it back.
        return $this->shapeMeeting($this->call($run, $config, 'GET', self::API_URL."/meetings/{$id}"));
    }
}
