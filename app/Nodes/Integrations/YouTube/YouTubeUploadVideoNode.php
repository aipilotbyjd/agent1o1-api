<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Ai\EndpointUrlGuard;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class YouTubeUploadVideoNode extends AbstractYouTubeNode
{
    private const int MAX_BYTES = 500 * 1024 * 1024;

    private const string UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos';

    public function type(): string
    {
        return 'google_youtube_upload_video';
    }

    public function name(): string
    {
        return 'YouTube: Upload Video';
    }

    public function description(): string
    {
        return 'Uploads a video from a link to the connected account\'s channel. Costs 1,600 of your daily YouTube quota units. Videos from API projects Google has not audited stay private.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['video_url', 'title'],
            'properties' => [
                ...$this->credentialFields(),
                'video_url' => Field::text('Video file link', 'A public https link to the video file (up to 500 MB).', 'https://example.com/video.mp4'),
                'title' => Field::text('Title', null, 'Launch day recap'),
                'description' => Field::textarea('Description'),
                'tags' => Field::list('Tags', null, 'tutorial'),
                'privacy' => $this->privacyField('private'),
                'made_for_kids' => Field::boolean('Made for kids', 'YouTube requires you to say whether the video is made for children.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $url = trim((string) $config['video_url']);

        if (! preg_match('#^https?://#i', $url) || ($problem = app(EndpointUrlGuard::class)->problem($url)) !== null) {
            throw new InvalidArgumentException('YouTube: '.($problem ?? 'the video link must start with https://.'));
        }

        $path = tempnam(sys_get_temp_dir(), 'yt-upload-');

        try {
            $download = Http::timeout(600)->sink($path)->get($url);

            if ($download->failed()) {
                throw new InvalidArgumentException("YouTube: could not download the video (HTTP {$download->status()}).");
            }

            $size = (int) filesize($path);

            if ($size === 0 || $size > self::MAX_BYTES) {
                throw new InvalidArgumentException('YouTube: the video file is empty or over the '.(self::MAX_BYTES / 1024 / 1024).' MB limit.');
            }

            $type = explode(';', (string) $download->header('Content-Type'))[0];
            $type = str_starts_with($type, 'video/') ? $type : 'video/*';

            $tags = array_values(array_filter(array_map('trim', array_map('strval', (array) ($config['tags'] ?? [])))));
            $token = $this->resolveAccessToken($run, $config);

            $session = Http::withToken($token)
                ->withHeaders(['X-Upload-Content-Type' => $type, 'X-Upload-Content-Length' => $size])
                ->withBody(json_encode([
                    'snippet' => array_filter([
                        'title' => $config['title'],
                        'description' => trim((string) ($config['description'] ?? '')) ?: null,
                        'tags' => $tags ?: null,
                    ]),
                    'status' => [
                        'privacyStatus' => $config['privacy'] ?? 'private',
                        'selfDeclaredMadeForKids' => (bool) ($config['made_for_kids'] ?? false),
                    ],
                ], JSON_THROW_ON_ERROR), 'application/json')
                ->post(self::UPLOAD_URL.'?'.http_build_query(['uploadType' => 'resumable', 'part' => 'snippet,status']));

            $this->assertOk($session);

            $location = (string) $session->header('Location');

            if ($location === '') {
                throw new RuntimeException('YouTube API error [/videos]: no upload address came back.');
            }

            // The stream owns the file handle and closes it when it is released.
            $upload = Http::withToken($token)->timeout(1800)->withBody(Utils::streamFor(fopen($path, 'rb')), $type)->put($location);

            $this->assertOk($upload);

            return $this->video((array) $upload->json());
        } finally {
            @unlink($path);
        }
    }

    /**
     * @throws RuntimeException when YouTube answers with an error status
     */
    private function assertOk(Response $response): void
    {
        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("YouTube API error [/videos]: {$message}");
        }
    }
}
