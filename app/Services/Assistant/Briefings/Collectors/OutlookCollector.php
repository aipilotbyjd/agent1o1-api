<?php

namespace App\Services\Assistant\Briefings\Collectors;

use App\Models\Assistant\Assistant;
use App\Models\Connectors\ConnectorCredential;
use App\Services\Assistant\Briefings\NodeReader;
use App\Services\Assistant\Briefings\SourceCollector;
use Carbon\CarbonInterface;

/**
 * Outlook is mail and calendar in one connection: new inbox mail since the
 * last report, plus the next day's meetings.
 */
class OutlookCollector implements SourceCollector
{
    public function __construct(private readonly NodeReader $reader) {}

    public function source(): string
    {
        return 'outlook';
    }

    public function collect(Assistant $assistant, ConnectorCredential $credential, CarbonInterface $since, int $limit): array
    {
        $mail = collect($this->reader->read('outlook_list_messages', $assistant, $credential, [
            'received_after' => $since->toIso8601String(),
            'max_results' => $limit,
        ])['value'] ?? [])
            ->map(fn (array $message): array => [
                'title' => (string) ($message['subject'] ?? '') ?: '(no subject)',
                'detail' => (string) ($message['bodyPreview'] ?? ''),
                'at' => $message['receivedDateTime'] ?? null,
                'url' => $message['webLink'] ?? null,
                'people' => array_values(array_filter([(string) ($message['from']['emailAddress']['address'] ?? '')])),
            ]);

        $events = collect($this->reader->read('outlook_list_events', $assistant, $credential, [
            'time_min' => now()->toIso8601String(),
            'time_max' => now()->addDay()->toIso8601String(),
            'max_results' => $limit,
        ])['value'] ?? [])
            ->reject(fn (array $event): bool => (bool) ($event['isCancelled'] ?? false))
            ->map(fn (array $event): array => [
                'title' => (string) ($event['subject'] ?? '') ?: '(untitled event)',
                'detail' => trim('Starts '.($event['start']['dateTime'] ?? 'unknown').' UTC. '.mb_substr((string) ($event['bodyPreview'] ?? ''), 0, 300)),
                'at' => isset($event['start']['dateTime']) ? now()->parse($event['start']['dateTime'], 'UTC')->toIso8601String() : null,
                'url' => $event['webLink'] ?? null,
                'people' => collect($event['attendees'] ?? [])->pluck('emailAddress.address')->filter()->values()->all(),
            ]);

        return $mail->concat($events)->take($limit)->values()->all();
    }
}
