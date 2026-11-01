<?php

namespace App\Nodes\AiAutomation;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Nodes\Support\FilePayload;
use Laravel\Ai\Transcription;

/**
 * Turns a recording (a call, a voice note, a meeting) into text with the
 * workspace's transcription provider.
 */
class AiTranscribeNode extends AbstractAiNode
{
    public function type(): string
    {
        return 'ai_transcribe';
    }

    public function name(): string
    {
        return 'AI Transcribe Audio';
    }

    public function icon(): string
    {
        return 'speech-to-text';
    }

    public function description(): string
    {
        return 'Turns an audio recording, such as a call, a voice note or a meeting, into text, with timestamped segments.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...FilePayload::inputFields('Recording'),
                'language' => Field::text('Language', 'Optional. The spoken language as a code or name, e.g. en. Leave empty to detect it.', 'en'),
                'speakers' => Field::advanced(Field::boolean('Tell speakers apart', 'Label who is speaking when the provider supports it.')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['title' => 'Transcript', 'description' => 'The full transcript.'],
                'segments' => ['title' => 'Segments', 'description' => 'The transcript in timed pieces, with the speaker when known.'],
                'usage' => self::usageSchema(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $file = FilePayload::read($config);

        if (! str_starts_with($file['mime_type'], 'audio/') && ! str_starts_with($file['mime_type'], 'video/') && $file['mime_type'] !== 'application/ogg') {
            throw new \InvalidArgumentException("This step transcribes audio or video, not {$file['mime_type']}.");
        }

        $pending = Transcription::fromBase64(base64_encode($file['content']), $file['mime_type'])->timeout(120);

        if (filled($config['language'] ?? null)) {
            $pending = $pending->language(trim((string) $config['language']));
        }

        if ((bool) ($config['speakers'] ?? false)) {
            $pending = $pending->diarize();
        }

        $response = $pending->generate();

        return [
            'text' => trim($response->text),
            'segments' => $response->segments->map(fn (mixed $segment): mixed => is_object($segment) && method_exists($segment, 'toArray') ? $segment->toArray() : $segment)->values()->all(),
            'usage' => [...$response->usage->toArray(), ...$response->meta->toArray()],
        ];
    }
}
