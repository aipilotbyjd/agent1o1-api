<?php

namespace App\Services\Agents\Knowledge\Readers;

use App\Enums\Agents\KnowledgeSourceType;
use App\Models\Agents\KnowledgeSource;
use App\Services\Agents\Knowledge\KnowledgeBatch;
use App\Services\Agents\Knowledge\KnowledgeDocumentData;
use Illuminate\Support\Carbon;

/**
 * A Slack channel's messages, one document per day. Each sync re-reads from
 * the start of the last synced day, so a day's document stays whole.
 */
class SlackReader implements KnowledgeReader
{
    use ReadsThroughNodes;

    public function type(): KnowledgeSourceType
    {
        return KnowledgeSourceType::Slack;
    }

    public function configRules(): array
    {
        return [
            'config.channel' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9]+$/'],
            'config.channel_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function read(KnowledgeSource $source, int $limit): KnowledgeBatch
    {
        $channel = (string) $source->config['channel'];
        $since = $source->sync_cursor !== null ? Carbon::createFromTimestamp((int) $source->sync_cursor)->startOfDay() : now()->subDays(30)->startOfDay();

        $messages = collect($this->node($source, 'slack_get_channel_history', [
            'channel' => $channel,
            'oldest' => (string) $since->getTimestamp(),
            'limit' => min($limit * 20, 1000),
        ])['messages'] ?? [])
            ->reject(fn (array $message): bool => isset($message['subtype']) || trim((string) ($message['text'] ?? '')) === '')
            ->sortBy('ts');

        $name = $source->config['channel_name'] ?? $channel;

        $documents = $messages
            ->groupBy(fn (array $message): string => Carbon::createFromTimestamp((int) $message['ts'])->toDateString())
            ->map(fn ($day, string $date): KnowledgeDocumentData => new KnowledgeDocumentData(
                "{$channel}:{$date}",
                "#{$name}: {$date}",
                $day->map(fn (array $message): string => Carbon::createFromTimestamp((int) $message['ts'])->format('H:i').' '.($message['user'] ?? 'someone').': '.$message['text'])->implode("\n"),
            ))
            ->values()
            ->all();

        $latest = $messages->last();

        return new KnowledgeBatch($documents, $latest !== null ? (string) (int) $latest['ts'] : null);
    }
}
