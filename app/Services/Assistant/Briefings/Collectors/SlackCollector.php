<?php

namespace App\Services\Assistant\Briefings\Collectors;

use App\Models\Assistant\Assistant;
use App\Models\Connectors\ConnectorCredential;
use App\Services\Assistant\Briefings\NodeReader;
use App\Services\Assistant\Briefings\SourceCollector;
use Carbon\CarbonInterface;

/**
 * Recent messages in channels the connected account is a member of.
 */
class SlackCollector implements SourceCollector
{
    private const int MAX_CHANNELS = 8;

    public function __construct(private readonly NodeReader $reader) {}

    public function source(): string
    {
        return 'slack';
    }

    public function collect(Assistant $assistant, ConnectorCredential $credential, CarbonInterface $since, int $limit): array
    {
        $channels = collect($this->reader->read('slack_list_channels', $assistant, $credential, [
            'types' => 'public_channel,private_channel',
            'limit' => 200,
        ])['channels'] ?? [])
            ->filter(fn (array $channel): bool => ($channel['is_member'] ?? false) && ! ($channel['is_archived'] ?? false))
            ->take(self::MAX_CHANNELS);

        return $channels
            ->flatMap(fn (array $channel): array => collect($this->reader->read('slack_get_channel_history', $assistant, $credential, [
                'channel' => $channel['id'],
                'oldest' => (string) $since->getTimestamp(),
                'limit' => 15,
            ])['messages'] ?? [])
                ->filter(fn (array $message): bool => filled($message['text'] ?? null) && ! isset($message['subtype']))
                ->map(fn (array $message): array => [
                    'title' => '#'.($channel['name'] ?? $channel['id']),
                    'detail' => mb_substr((string) $message['text'], 0, 500),
                    'at' => isset($message['ts']) ? now()->setTimestamp((int) $message['ts'])->toIso8601String() : null,
                    'url' => null,
                    'people' => array_values(array_filter([(string) ($message['user'] ?? '')])),
                ])
                ->all())
            ->take($limit)
            ->values()
            ->all();
    }
}
