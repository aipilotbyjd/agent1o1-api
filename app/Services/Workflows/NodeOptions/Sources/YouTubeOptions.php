<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;

/**
 * The member's own playlists.
 */
class YouTubeOptions extends HttpOptionsSource
{
    private const string PLAYLISTS_URL = 'https://www.googleapis.com/youtube/v3/playlists';

    public function sources(): array
    {
        return ['google_youtube.playlists'];
    }

    protected function appName(): string
    {
        return 'YouTube';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->getJson($query, self::PLAYLISTS_URL, [
            'part' => 'snippet,contentDetails',
            'mine' => 'true',
            'maxResults' => 50,
            'pageToken' => $query->cursor,
        ]);

        $options = collect($body['items'] ?? [])
            ->filter(fn (mixed $playlist): bool => is_array($playlist) && isset($playlist['id']) && $query->matches((string) ($playlist['snippet']['title'] ?? '')))
            ->map(fn (array $playlist): NodeOption => new NodeOption(
                (string) $playlist['id'],
                $playlist['snippet']['title'] ?? null,
                isset($playlist['contentDetails']['itemCount']) ? $playlist['contentDetails']['itemCount'].' videos' : null,
            ));

        return new NodeOptionsPage($options, $body['nextPageToken'] ?? null);
    }
}
