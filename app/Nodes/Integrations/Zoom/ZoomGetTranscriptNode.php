<?php

namespace App\Nodes\Integrations\Zoom;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

/**
 * Fetches a meeting's transcript, ready to hand to AI Summarize for meeting
 * notes. Zoom must have transcription turned on and the recording finished.
 */
class ZoomGetTranscriptNode extends AbstractZoomNode
{
    private const int MAX_TRANSCRIPT_BYTES = 2_000_000;

    public function type(): string
    {
        return 'zoom_get_transcript';
    }

    public function name(): string
    {
        return 'Zoom: Get Transcript';
    }

    public function description(): string
    {
        return 'Gets a recorded meeting’s transcript as plain text with speaker names, ready to summarise.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['meeting_id'],
            'properties' => [
                ...$this->credentialFields(),
                'meeting_id' => Field::text('Meeting ID', 'From List Recordings, or the New recording trigger.', '85012345678'),
                'timestamps' => Field::advanced(Field::boolean('Keep timestamps', 'Adds the time before each line.', false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->meetingId($config);
        $recording = $this->call($run, $config, 'GET', self::API_URL."/meetings/{$id}/recordings");

        $file = collect((array) ($recording['recording_files'] ?? []))->first(fn (mixed $candidate): bool => ($candidate['file_type'] ?? '') === 'TRANSCRIPT' && filled($candidate['download_url'] ?? null));

        if ($file === null) {
            throw new RuntimeException('This meeting has no transcript yet. Turn on cloud recording with audio transcript in Zoom, and wait for the recording to finish processing.');
        }

        $download = $this->download($run, $config, (string) $file['download_url']);

        if (strlen($download['content']) > self::MAX_TRANSCRIPT_BYTES) {
            throw new RuntimeException('The transcript is too long to bring into a workflow.');
        }

        $text = $this->readableText($download['content'], (bool) ($config['timestamps'] ?? false));

        return ['transcript' => $text, 'topic' => $recording['topic'] ?? null, 'meeting_id' => $id, 'started_at' => $recording['start_time'] ?? null, 'characters' => mb_strlen($text)];
    }

    /**
     * Zoom's transcript is WebVTT: cue numbers, time ranges and "Speaker: line" text.
     */
    private function readableText(string $vtt, bool $timestamps): string
    {
        $lines = [];
        $time = null;

        foreach (preg_split('/\R/', $vtt) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || $line === 'WEBVTT' || preg_match('/^\d+$/', $line) === 1) {
                continue;
            }

            if (preg_match('/^(\d{2}:\d{2}:\d{2})(?:\.\d+)?\s+-->/', $line, $match) === 1) {
                $time = $match[1];

                continue;
            }

            $lines[] = ($timestamps && $time !== null ? "[{$time}] " : '').$line;
            $time = null;
        }

        return trim(implode("\n", $lines));
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'transcript' => ['title' => 'Transcript', 'description' => 'The meeting as text, one line per turn, with speaker names. Pass it to AI Summarize.'],
                'topic' => ['title' => 'Topic', 'description' => 'The meeting’s title.'],
                'meeting_id' => ['title' => 'Meeting ID', 'description' => 'The meeting.'],
                'started_at' => ['title' => 'Started at', 'description' => 'When it started.'],
                'characters' => ['title' => 'Length', 'description' => 'The transcript’s length in characters.'],
            ],
        ];
    }
}
