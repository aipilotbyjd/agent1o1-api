<?php

namespace App\Nodes\Integrations\Zoom;

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ZoomListRecordingsNode extends AbstractZoomNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_DAYS = 14;

    public function type(): string
    {
        return 'zoom_list_recordings';
    }

    public function name(): string
    {
        return 'Zoom: List Recordings';
    }

    public function description(): string
    {
        return 'Lists your cloud recordings, newest first. As a trigger it starts the flow when a new recording is ready.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'from' => Field::datetime('From', 'Leave empty for the last 30 days.'),
                'limit' => Field::integer('Most recordings', null, 30, 1, 300),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $from = filled($config['from'] ?? null) ? $this->utc($config['from'], null, 'The start date') : now()->subDays(30)->toImmutable();

        $body = $this->call($run, $config, 'GET', self::API_URL.'/users/me/recordings', query: [
            'from' => $from->format('Y-m-d'),
            'to' => now()->addDay()->format('Y-m-d'),
            'page_size' => max(1, min(300, (int) ($config['limit'] ?? 30))),
        ]);

        $recordings = array_map(fn (mixed $meeting): array => [
            'meeting_id' => isset($meeting['id']) ? (string) $meeting['id'] : null,
            'uuid' => $meeting['uuid'] ?? null,
            'topic' => $meeting['topic'] ?? null,
            'started_at' => $meeting['start_time'] ?? null,
            'duration' => $meeting['duration'] ?? null,
            'share_url' => $meeting['share_url'] ?? null,
            'has_transcript' => collect((array) ($meeting['recording_files'] ?? []))->contains(fn (mixed $file): bool => ($file['file_type'] ?? '') === 'TRANSCRIPT'),
            'files' => array_map(fn (mixed $file): array => ['type' => $file['file_type'] ?? null, 'kind' => $file['recording_type'] ?? null, 'size' => $file['file_size'] ?? null, 'status' => $file['status'] ?? null], (array) ($meeting['recording_files'] ?? [])),
        ], (array) ($body['meetings'] ?? []));

        return ['recordings' => $recordings, 'count' => count($recordings)];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'recordings' => ['title' => 'Recordings', 'description' => 'Each: meeting_id, topic, started_at, duration, share_url, has_transcript and files.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'recordings';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['uuid'] ?? $item['meeting_id'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Zoom: New recording';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Zoom meeting’s cloud recording is ready, for example to fetch the transcript and write the meeting notes.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'from' => now()->subDays(self::TRIGGER_WINDOW_DAYS)->toIso8601String(), 'limit' => 100];
    }

    public function triggerPollMinutes(): int
    {
        return 10;
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
