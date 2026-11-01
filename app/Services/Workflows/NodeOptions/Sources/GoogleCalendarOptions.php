<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;

/**
 * Calendars on the member's list, and upcoming events in one of them.
 */
class GoogleCalendarOptions extends HttpOptionsSource
{
    private const string BASE_URL = 'https://www.googleapis.com/calendar/v3';

    private const int EVENTS_PAGE_SIZE = 50;

    public function sources(): array
    {
        return ['google_calendar.calendars', 'google_calendar.events'];
    }

    protected function appName(): string
    {
        return 'Google Calendar';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return $source === 'google_calendar.events' ? $this->events($query) : $this->calendars($query);
    }

    /**
     * Primary calendar first, as the value `primary`: what the nodes
     * default to anyway.
     */
    private function calendars(NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->getJson($query, self::BASE_URL.'/users/me/calendarList', [
            'maxResults' => 250,
            'pageToken' => $query->cursor,
        ]);

        $options = collect($body['items'] ?? [])
            ->filter(fn (mixed $calendar): bool => is_array($calendar) && isset($calendar['id']))
            ->filter(fn (array $calendar): bool => $query->matches((string) ($calendar['summaryOverride'] ?? $calendar['summary'] ?? '')))
            ->sortByDesc(fn (array $calendar): bool => (bool) ($calendar['primary'] ?? false))
            ->map(fn (array $calendar): NodeOption => ($calendar['primary'] ?? false)
                ? new NodeOption('primary', $calendar['summaryOverride'] ?? $calendar['summary'] ?? null, 'Primary calendar')
                : new NodeOption((string) $calendar['id'], $calendar['summaryOverride'] ?? $calendar['summary'] ?? null));

        return new NodeOptionsPage($options, $body['nextPageToken'] ?? null);
    }

    /**
     * Events from now on, soonest first, in the node's calendar (primary by
     * default). Search is Calendar's own free-text `q`.
     */
    private function events(NodeOptionsQuery $query): NodeOptionsPage
    {
        $calendarId = rawurlencode((string) $query->configString('calendar_id', 'primary'));

        $body = $this->getJson($query, self::BASE_URL."/calendars/{$calendarId}/events", [
            'timeMin' => now()->toRfc3339String(),
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => self::EVENTS_PAGE_SIZE,
            'q' => $query->search,
            'pageToken' => $query->cursor,
        ]);

        $options = collect($body['items'] ?? [])
            ->filter(fn (mixed $event): bool => is_array($event) && isset($event['id']))
            ->map(fn (array $event): NodeOption => new NodeOption(
                (string) $event['id'],
                ($event['summary'] ?? '') ?: '(no title)',
                $event['start']['dateTime'] ?? $event['start']['date'] ?? null,
            ));

        return new NodeOptionsPage($options, $body['nextPageToken'] ?? null);
    }
}
