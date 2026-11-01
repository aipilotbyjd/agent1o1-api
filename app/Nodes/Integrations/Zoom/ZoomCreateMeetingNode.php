<?php

namespace App\Nodes\Integrations\Zoom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZoomCreateMeetingNode extends AbstractZoomNode
{
    public function type(): string
    {
        return 'zoom_create_meeting';
    }

    public function name(): string
    {
        return 'Zoom: Create Meeting';
    }

    public function description(): string
    {
        return 'Creates a Zoom meeting at a set time, or an instant one, and returns the link to share with attendees.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['topic'],
            'properties' => [
                ...$this->credentialFields(),
                'topic' => Field::text('Topic', 'The meeting’s title.', 'Intro call with {{ nodes.lead.company }}'),
                'start_at' => Field::datetime('Starts at', 'Leave empty for an instant meeting that can be started right away.'),
                'timezone' => Field::text('Timezone', 'How to read the start time, e.g. Europe/London. Defaults to UTC.', 'Europe/London'),
                'duration' => Field::integer('Length (minutes)', null, 30, 1, 1440),
                'agenda' => Field::textarea('Agenda'),
                'password' => Field::advanced(Field::text('Passcode', 'Up to 10 letters, numbers or @ - _ *. Leave empty to let Zoom choose one.')),
                'waiting_room' => Field::advanced(Field::boolean('Waiting room', 'Attendees wait until the host lets them in.', true)),
                'join_before_host' => Field::advanced(Field::boolean('Let attendees join before the host', null, false)),
                'mute_upon_entry' => Field::advanced(Field::boolean('Mute attendees on entry', null, true)),
                'auto_recording' => Field::advanced(Field::select('Record', ['none' => 'Don’t record', 'cloud' => 'To the cloud', 'local' => 'On the host’s computer'], null, 'none')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $topic = trim((string) ($config['topic'] ?? ''));

        if ($topic === '') {
            throw new InvalidArgumentException('Give the meeting a topic.');
        }

        $password = trim((string) ($config['password'] ?? ''));

        if ($password !== '' && preg_match('/^[A-Za-z0-9@\-_*]{1,10}$/', $password) !== 1) {
            throw new InvalidArgumentException('A Zoom passcode can have at most 10 letters, numbers or @ - _ *.');
        }

        $scheduled = filled($config['start_at'] ?? null);

        $meeting = $this->call($run, $config, 'POST', self::API_URL.'/users/me/meetings', array_filter([
            'topic' => mb_substr($topic, 0, 200),
            'type' => $scheduled ? 2 : 1,
            'start_time' => $scheduled ? $this->utc($config['start_at'], $config['timezone'] ?? null, 'The start time')->format('Y-m-d\TH:i:s\Z') : null,
            'timezone' => $scheduled ? 'UTC' : null,
            'duration' => $this->minutes($config['duration'] ?? null),
            'agenda' => filled($config['agenda'] ?? null) ? mb_substr(trim((string) $config['agenda']), 0, 2000) : null,
            'password' => $password !== '' ? $password : null,
            'settings' => [
                'waiting_room' => (bool) ($config['waiting_room'] ?? true),
                'join_before_host' => (bool) ($config['join_before_host'] ?? false),
                'mute_upon_entry' => (bool) ($config['mute_upon_entry'] ?? true),
                'auto_recording' => in_array($config['auto_recording'] ?? 'none', ['none', 'cloud', 'local'], true) ? $config['auto_recording'] ?? 'none' : 'none',
            ],
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeMeeting($meeting);
    }
}
