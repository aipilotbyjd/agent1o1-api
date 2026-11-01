<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class YouTubeGetCaptionsNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_get_captions';
    }

    public function name(): string
    {
        return 'YouTube: Get Captions';
    }

    public function description(): string
    {
        return 'Reads the captions (transcript) of one of your own videos. YouTube only gives captions to the video\'s owner. Costs about 250 quota units.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['video_id'],
            'properties' => [
                ...$this->credentialFields(),
                'video_id' => Field::text('Video ID or link', 'A video on the connected account\'s channel.', 'dQw4w9WgXcQ'),
                'language' => Field::text('Language', 'A language code such as en. Leave empty to use the first caption track.', 'en'),
                'format' => Field::select('Format', ['srt' => 'SubRip (.srt)', 'vtt' => 'WebVTT (.vtt)'], null, 'srt'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $videoId = $this->videoIdFromLink((string) $config['video_id']);
        $language = strtolower(trim((string) ($config['language'] ?? '')));

        $tracks = (array) ($this->get($run, '/captions', $config, ['part' => 'snippet', 'videoId' => $videoId])['items'] ?? []);

        $track = collect($tracks)->first(fn (array $item): bool => $language === '' || strtolower((string) ($item['snippet']['language'] ?? '')) === $language);

        if (! is_array($track)) {
            throw new RuntimeException("YouTube: no captions found for the video \"{$videoId}\"".($language !== '' ? " in \"{$language}\"" : '').'.');
        }

        $format = $config['format'] ?? 'srt';

        return [
            'video_id' => $videoId,
            'caption_id' => $track['id'],
            'language' => $track['snippet']['language'] ?? null,
            'format' => $format,
            'captions' => $this->getText($run, '/captions/'.rawurlencode((string) $track['id']), $config, ['tfmt' => $format]),
        ];
    }
}
