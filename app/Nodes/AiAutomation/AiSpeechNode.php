<?php

namespace App\Nodes\AiAutomation;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Nodes\Support\FilePayload;
use Laravel\Ai\Audio;

/**
 * Reads text aloud as an audio file: a spoken summary, a voice message, a
 * narration. The file comes out in the shape other steps can attach.
 */
class AiSpeechNode extends AbstractAiNode
{
    private const int MAX_CHARACTERS = 4000;

    public function type(): string
    {
        return 'ai_speech';
    }

    public function name(): string
    {
        return 'AI Text to Speech';
    }

    public function icon(): string
    {
        return 'volume-high';
    }

    public function description(): string
    {
        return 'Reads text aloud and returns an audio file, for a spoken summary, a voice message or a narration.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['text'],
            'properties' => [
                'text' => Field::textarea('Text to speak', 'Up to '.number_format(self::MAX_CHARACTERS).' characters. Use {{templates}} to take it from an earlier step.'),
                'voice' => Field::select('Voice', ['default' => 'Default', 'female' => 'Female', 'male' => 'Male'], null, 'default'),
                'style' => Field::advanced(Field::text('Delivery', 'Optional. How it should sound, e.g. calm and warm.', 'calm and warm')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file' => ['title' => 'Audio file', 'description' => 'The audio as a file: filename, mime_type, size and content_base64. Attach it with {{ nodes.speech.file }}.'],
                'size' => ['title' => 'Size', 'description' => 'The file size in bytes.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $text = $this->requiredText($config, 'text', 'The text to speak');

        if (mb_strlen($text) > self::MAX_CHARACTERS) {
            throw new \InvalidArgumentException('The text is too long to speak in one go ('.number_format(mb_strlen($text)).' characters; the limit is '.number_format(self::MAX_CHARACTERS).'). Split it up.');
        }

        $pending = Audio::of($text)->timeout(120);

        $pending = match ($config['voice'] ?? 'default') {
            'female' => $pending->female(),
            'male' => $pending->male(),
            default => $pending,
        };

        if (filled($config['style'] ?? null)) {
            $pending = $pending->instructions(trim((string) $config['style']));
        }

        $response = $pending->generate();
        $mime = $response->mimeType() ?: 'audio/mpeg';
        $file = FilePayload::make($response->content(), 'speech.'.(str_contains($mime, 'wav') ? 'wav' : (str_contains($mime, 'ogg') ? 'ogg' : 'mp3')), $mime);

        return ['file' => $file, 'size' => $file['size']];
    }
}
