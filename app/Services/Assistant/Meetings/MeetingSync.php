<?php

namespace App\Services\Assistant\Meetings;

use App\Enums\Assistant\AssistantMeetingPrepStatus;
use App\Models\Assistant\Assistant;
use App\Models\Connectors\ConnectorCredential;
use App\Services\Assistant\Briefings\NodeReader;
use App\Services\Assistant\Tools\ConnectorToolProvider;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Copies the owner's next few days of meetings from Google Calendar (or
 * Outlook when that's the calendar connected), so they can be listed and
 * prepped. Events with no other guests are not
 * meetings and are left out; meetings that disappeared from the calendar
 * (cancelled, moved) are dropped unless a brief was already written.
 */
class MeetingSync
{
    private const int MAX_EVENTS = 50;

    public function __construct(
        private readonly NodeReader $reader,
        private readonly ConnectorToolProvider $connectors,
    ) {}

    /**
     * Calendar connectors, preferred first.
     */
    private const array CALENDARS = ['google_calendar', 'outlook'];

    public function isConnected(Assistant $assistant): bool
    {
        return $this->connectors->credentialsFor($assistant)->hasAny(self::CALENDARS);
    }

    /**
     * @return int how many upcoming meetings are now known
     */
    public function sync(Assistant $assistant): int
    {
        $credentials = $this->connectors->credentialsFor($assistant)->only(self::CALENDARS);
        $source = collect(self::CALENDARS)->first(fn (string $key): bool => $credentials->has($key));

        if ($source === null) {
            return 0;
        }

        $ownerEmail = Str::lower((string) $assistant->user->email);
        $until = now()->addDays((int) config('assistant.meetings.sync_days'));

        $events = ($source === 'outlook'
            ? $this->outlookEvents($assistant, $credentials->get($source), $until)
            : $this->googleEvents($assistant, $credentials->get($source)))
            ->filter(fn (array $event): bool => isset($event['start']['dateTime']) && ($event['status'] ?? 'confirmed') !== 'cancelled')
            ->filter(fn (array $event): bool => now()->parse($event['start']['dateTime'])->lte($until));

        $seen = [];

        foreach ($events as $event) {
            $attendees = collect($event['attendees'] ?? [])
                ->reject(fn (array $attendee): bool => ($attendee['self'] ?? false) || ($attendee['resource'] ?? false) || Str::lower($attendee['email'] ?? '') === $ownerEmail)
                ->map(fn (array $attendee): array => ['email' => Str::lower((string) ($attendee['email'] ?? '')), 'name' => $attendee['displayName'] ?? null])
                ->filter(fn (array $attendee): bool => $attendee['email'] !== '')
                ->values()
                ->all();

            if ($attendees === []) {
                continue;
            }

            $startsAt = now()->parse($event['start']['dateTime'])->utc();

            $meeting = $assistant->meetings()->updateOrCreate(
                ['provider_event_id' => (string) $event['id'], 'starts_at' => $startsAt],
                [
                    'title' => Str::limit((string) ($event['summary'] ?? 'Untitled meeting'), 250),
                    'ends_at' => isset($event['end']['dateTime']) ? now()->parse($event['end']['dateTime'])->utc() : null,
                    'attendees' => $attendees,
                    'is_external' => ExternalMeeting::isExternal($ownerEmail, $attendees),
                    'html_link' => $event['htmlLink'] ?? null,
                    'description' => isset($event['description']) ? Str::limit(strip_tags((string) $event['description']), 2000) : null,
                ],
            );

            $seen[] = $meeting->id;
        }

        $assistant->meetings()
            ->where('starts_at', '>', now())
            ->whereNotIn('id', $seen)
            ->where('prep_status', AssistantMeetingPrepStatus::None)
            ->delete();

        return count($seen);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function googleEvents(Assistant $assistant, ConnectorCredential $credential): Collection
    {
        return collect($this->reader->read('google_calendar_list_events', $assistant, $credential, [
            'time_min' => now()->toRfc3339String(),
            'max_results' => self::MAX_EVENTS,
        ])['items'] ?? []);
    }

    /**
     * Outlook events in Google Calendar's shape, so one loop handles both.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function outlookEvents(Assistant $assistant, ConnectorCredential $credential, CarbonInterface $until): Collection
    {
        return collect($this->reader->read('outlook_list_events', $assistant, $credential, [
            'time_min' => now()->toIso8601String(),
            'time_max' => $until->toIso8601String(),
            'max_results' => self::MAX_EVENTS,
        ])['value'] ?? [])
            ->map(fn (array $event): array => [
                'id' => $event['id'],
                'status' => ($event['isCancelled'] ?? false) ? 'cancelled' : 'confirmed',
                'summary' => $event['subject'] ?? null,
                'description' => $event['bodyPreview'] ?? null,
                'htmlLink' => $event['webLink'] ?? null,
                'start' => ['dateTime' => isset($event['start']['dateTime']) ? now()->parse($event['start']['dateTime'], 'UTC')->toIso8601String() : null],
                'end' => ['dateTime' => isset($event['end']['dateTime']) ? now()->parse($event['end']['dateTime'], 'UTC')->toIso8601String() : null],
                'attendees' => collect($event['attendees'] ?? [])
                    ->map(fn (array $attendee): array => [
                        'email' => $attendee['emailAddress']['address'] ?? '',
                        'displayName' => $attendee['emailAddress']['name'] ?? null,
                        'resource' => ($attendee['type'] ?? '') === 'resource',
                    ])
                    ->all(),
            ]);
    }
}
