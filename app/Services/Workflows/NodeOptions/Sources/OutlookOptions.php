<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;

/**
 * Outlook mail folders, recent messages and upcoming events, via Microsoft
 * Graph. Pages are `$skip` offsets, never Graph's `@odata.nextLink`, which
 * is a full URL the client could swap for any other.
 */
class OutlookOptions extends HttpOptionsSource
{
    private const string BASE_URL = 'https://graph.microsoft.com/v1.0';

    private const int FOLDERS_PAGE_SIZE = 100;

    private const int PAGE_SIZE = 25;

    private const int EVENTS_WINDOW_DAYS = 30;

    public function sources(): array
    {
        return ['outlook.folders', 'outlook.messages', 'outlook.events', 'outlook.calendars'];
    }

    protected function appName(): string
    {
        return 'Outlook';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return parent::http($query)->withHeaders(['Prefer' => 'outlook.timezone="UTC"']);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'outlook.messages' => $this->messages($query),
            'outlook.events' => $this->events($query),
            'outlook.calendars' => $this->calendars($query),
            default => $this->folders($query),
        };
    }

    private function folders(NodeOptionsQuery $query): NodeOptionsPage
    {
        $skip = $this->intCursor($query, 0);
        $folders = $this->values($this->getJson($query, self::BASE_URL.'/me/mailFolders', [
            '$top' => self::FOLDERS_PAGE_SIZE,
            '$skip' => $skip,
            '$select' => 'id,displayName,totalItemCount',
        ]));

        $options = collect($folders)
            ->filter(fn (array $folder): bool => $query->matches((string) ($folder['displayName'] ?? '')))
            ->map(fn (array $folder): NodeOption => new NodeOption(
                (string) $folder['id'],
                $folder['displayName'] ?? null,
                isset($folder['totalItemCount']) ? "{$folder['totalItemCount']} messages" : null,
            ));

        return new NodeOptionsPage($options, count($folders) === self::FOLDERS_PAGE_SIZE ? $skip + self::FOLDERS_PAGE_SIZE : null);
    }

    /**
     * Newest first from the inbox. Graph doesn't allow `$search` together
     * with `$orderby` or `$skip`, so a search returns one page of the best
     * matches.
     */
    private function messages(NodeOptionsQuery $query): NodeOptionsPage
    {
        $skip = $this->intCursor($query, 0);
        $params = ['$top' => self::PAGE_SIZE, '$select' => 'id,subject,from,receivedDateTime'];
        $searching = $query->search !== null;

        if ($searching) {
            $params['$search'] = '"'.str_replace('"', '', (string) $query->search).'"';
        } else {
            $params['$orderby'] = 'receivedDateTime desc';
            $params['$skip'] = $skip;
        }

        $messages = $this->values($this->getJson($query, self::BASE_URL.'/me/mailFolders/inbox/messages', $params));

        $options = collect($messages)->map(fn (array $message): NodeOption => new NodeOption(
            (string) $message['id'],
            ($message['subject'] ?? '') ?: '(no subject)',
            $message['from']['emailAddress']['name'] ?? $message['from']['emailAddress']['address'] ?? null,
        ));

        return new NodeOptionsPage($options, ! $searching && count($messages) === self::PAGE_SIZE ? $skip + self::PAGE_SIZE : null);
    }

    private function calendars(NodeOptionsQuery $query): NodeOptionsPage
    {
        $skip = $this->intCursor($query, 0);
        $calendars = $this->values($this->getJson($query, self::BASE_URL.'/me/calendars', [
            '$top' => self::FOLDERS_PAGE_SIZE,
            '$skip' => $skip,
            '$select' => 'id,name,isDefaultCalendar,owner',
        ]));

        $options = collect($calendars)
            ->filter(fn (array $calendar): bool => $query->matches((string) ($calendar['name'] ?? '')))
            ->map(fn (array $calendar): NodeOption => new NodeOption(
                (string) $calendar['id'],
                ($calendar['name'] ?? '') ?: '(no name)',
                ($calendar['isDefaultCalendar'] ?? false) ? 'Main calendar' : ($calendar['owner']['address'] ?? null),
            ));

        return new NodeOptionsPage($options, count($calendars) === self::FOLDERS_PAGE_SIZE ? $skip + self::FOLDERS_PAGE_SIZE : null);
    }

    private function events(NodeOptionsQuery $query): NodeOptionsPage
    {
        $skip = $this->intCursor($query, 0);
        $events = $this->values($this->getJson($query, self::BASE_URL.'/me/calendarView', [
            'startDateTime' => now()->utc()->toIso8601String(),
            'endDateTime' => now()->utc()->addDays(self::EVENTS_WINDOW_DAYS)->toIso8601String(),
            '$orderby' => 'start/dateTime',
            '$top' => self::PAGE_SIZE,
            '$skip' => $skip,
            '$select' => 'id,subject,start',
        ]));

        $options = collect($events)
            ->filter(fn (array $event): bool => $query->matches((string) ($event['subject'] ?? '')))
            ->map(fn (array $event): NodeOption => new NodeOption(
                (string) $event['id'],
                ($event['subject'] ?? '') ?: '(no title)',
                $event['start']['dateTime'] ?? null,
            ));

        return new NodeOptionsPage($options, count($events) === self::PAGE_SIZE ? $skip + self::PAGE_SIZE : null);
    }

    /**
     * Graph's `value` list, minus anything without an id.
     *
     * @param  array<mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function values(array $body): array
    {
        return array_values(array_filter(
            is_array($body['value'] ?? null) ? $body['value'] : [],
            fn (mixed $item): bool => is_array($item) && isset($item['id']),
        ));
    }
}
