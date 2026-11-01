<?php

namespace App\Services\Assistant\Meetings;

use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantMeeting;
use App\Services\Assistant\Briefings\NodeReader;
use App\Services\Assistant\Tools\ConnectorToolProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * What the owner's apps say about a meeting's people and topic: read only,
 * and each app on its own, so one failing app doesn't stop the brief.
 */
class MeetingResearch
{
    public function __construct(
        private readonly NodeReader $reader,
        private readonly ConnectorToolProvider $connectors,
    ) {}

    /**
     * @param  list<string>|null  $onlySources  null = every connected app
     * @return array{items: Collection<int, array<string, mixed>>, results: Collection<string, array<string, mixed>>}
     */
    public function for(Assistant $assistant, AssistantMeeting $meeting, ?array $onlySources = null): array
    {
        $credentials = $this->connectors->credentialsFor($assistant)
            ->only(['gmail', 'outlook', 'google_drive'])
            ->when($onlySources !== null, fn (Collection $credentials) => $credentials->only($onlySources));

        $items = collect();
        $results = collect();

        foreach ($credentials as $source => $credential) {
            try {
                $found = match ($source) {
                    'gmail' => $this->emails($assistant, $credential, $meeting),
                    'outlook' => $this->outlookEmails($assistant, $credential, $meeting),
                    'google_drive' => $this->files($assistant, $credential, $meeting),
                };

                $items = $items->concat(array_map(fn (array $item): array => [...$item, 'source' => $source], $found));
                $results->put($source, ['source' => $source, 'name' => $credential->connector->name, 'ok' => true, 'items' => count($found)]);
            } catch (Throwable $e) {
                report($e);
                $results->put($source, ['source' => $source, 'name' => $credential->connector->name, 'ok' => false, 'error' => Str::limit($e->getMessage(), 200)]);
            }
        }

        return ['items' => $items, 'results' => $results];
    }

    /**
     * Recent mail to or from any of the guests.
     *
     * @return list<array<string, mixed>>
     */
    private function emails(Assistant $assistant, $credential, AssistantMeeting $meeting): array
    {
        $people = collect($meeting->attendees ?? [])->pluck('email')->take(10);

        if ($people->isEmpty()) {
            return [];
        }

        $who = $people->map(fn (string $email): string => "from:{$email} OR to:{$email}")->implode(' OR ');
        $days = (int) config('assistant.meetings.email_lookback_days');

        $listed = $this->reader->read('gmail_list_messages', $assistant, $credential, [
            'query' => "({$who}) newer_than:{$days}d",
            'max_results' => (int) config('assistant.meetings.max_emails'),
        ]);

        return collect($listed['messages'] ?? [])
            ->map(function (array $listedMessage) use ($assistant, $credential): array {
                $message = $this->reader->read('gmail_get_message', $assistant, $credential, ['message_id' => $listedMessage['id']]);
                $headers = collect($message['payload']['headers'] ?? [])->mapWithKeys(fn (array $header): array => [Str::lower($header['name'] ?? '') => $header['value'] ?? '']);

                return [
                    'title' => (string) ($headers['subject'] ?? '(no subject)'),
                    'detail' => html_entity_decode((string) ($message['snippet'] ?? ''), ENT_QUOTES),
                    'at' => (string) ($headers['date'] ?? ''),
                    'people' => array_values(array_filter([(string) ($headers['from'] ?? '')])),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Recent Outlook mail from any of the guests (Graph search can't combine
     * people with a date, so the newest few per guest).
     *
     * @return list<array<string, mixed>>
     */
    private function outlookEmails(Assistant $assistant, $credential, AssistantMeeting $meeting): array
    {
        $max = (int) config('assistant.meetings.max_emails');
        $since = now()->subDays((int) config('assistant.meetings.email_lookback_days'));

        return collect($meeting->attendees ?? [])->pluck('email')->take(5)
            ->flatMap(fn (string $email): array => $this->reader->read('outlook_list_messages', $assistant, $credential, [
                'folder' => 'inbox',
                'search' => "participants:{$email}",
                'max_results' => $max,
            ])['value'] ?? [])
            ->filter(fn (array $message): bool => isset($message['receivedDateTime']) && now()->parse($message['receivedDateTime'])->gte($since))
            ->unique('id')
            ->sortByDesc('receivedDateTime')
            ->take($max)
            ->map(fn (array $message): array => [
                'title' => (string) ($message['subject'] ?? '') ?: '(no subject)',
                'detail' => (string) ($message['bodyPreview'] ?? ''),
                'at' => (string) $message['receivedDateTime'],
                'people' => array_values(array_filter([(string) ($message['from']['emailAddress']['address'] ?? '')])),
            ])
            ->values()
            ->all();
    }

    /**
     * Files whose text mentions the meeting's topic.
     *
     * @return list<array<string, mixed>>
     */
    private function files(Assistant $assistant, $credential, AssistantMeeting $meeting): array
    {
        $topic = trim(Str::limit(preg_replace('/[^\pL\pN\s-]/u', ' ', $meeting->title) ?? '', 60, ''));

        if ($topic === '') {
            return [];
        }

        $result = $this->reader->read('google_drive_list_files', $assistant, $credential, [
            'query' => "fullText contains '".str_replace("'", '', $topic)."' and trashed = false",
            'page_size' => (int) config('assistant.meetings.max_files'),
        ]);

        return collect($result['files'] ?? [])
            ->map(fn (array $file): array => [
                'title' => (string) ($file['name'] ?? 'Untitled file'),
                'detail' => 'Related file',
                'at' => null,
                'people' => [],
            ])
            ->values()
            ->all();
    }
}
