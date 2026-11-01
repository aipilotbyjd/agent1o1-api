<?php

namespace App\Services\Assistant\Briefings\Collectors;

use App\Models\Assistant\Assistant;
use App\Models\Connectors\ConnectorCredential;
use App\Services\Assistant\Briefings\NodeReader;
use App\Services\Assistant\Briefings\SourceCollector;
use Carbon\CarbonInterface;

/**
 * The next day's schedule: a calendar is about what's coming, not what
 * changed, so it ignores `$since`.
 */
class GoogleCalendarCollector implements SourceCollector
{
    public function __construct(private readonly NodeReader $reader) {}

    public function source(): string
    {
        return 'google_calendar';
    }

    public function collect(Assistant $assistant, ConnectorCredential $credential, CarbonInterface $since, int $limit): array
    {
        $result = $this->reader->read('google_calendar_list_events', $assistant, $credential, [
            'time_min' => now()->toRfc3339String(),
            'max_results' => $limit,
        ]);

        $until = now()->addDay();

        return collect($result['items'] ?? [])
            ->map(function (array $event): array {
                $start = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;

                return [
                    'title' => (string) ($event['summary'] ?? '(untitled event)'),
                    'detail' => trim('Starts '.($start ?? 'unknown').'. '.mb_substr((string) ($event['description'] ?? ''), 0, 300)),
                    'at' => $start,
                    'url' => $event['htmlLink'] ?? null,
                    'people' => collect($event['attendees'] ?? [])->pluck('email')->filter()->values()->all(),
                ];
            })
            ->filter(fn (array $item): bool => $item['at'] === null || now()->parse($item['at'])->lte($until))
            ->values()
            ->all();
    }
}
