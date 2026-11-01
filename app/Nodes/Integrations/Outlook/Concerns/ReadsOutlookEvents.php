<?php

namespace App\Nodes\Integrations\Outlook\Concerns;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * What the Outlook calendar trigger nodes share: the optional calendar, reading
 * events in an order, and telling a cancelled event or one the person declined.
 */
trait ReadsOutlookEvents
{
    private const string EVENT_FIELDS = 'id,subject,bodyPreview,start,end,attendees,organizer,webLink,isCancelled,isAllDay,isOnlineMeeting,onlineMeeting,location,responseStatus,createdDateTime,lastModifiedDateTime';

    /**
     * The calendar picker; empty means the main calendar.
     *
     * @return array<string, mixed>
     */
    protected function calendarField(): array
    {
        return Field::dynamic('Calendar', 'outlook.calendars', 'Defaults to your main calendar.');
    }

    /**
     * Events of a calendar in an order, newest first by the given property.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    protected function eventsOrderedBy(Run $run, array $config, string $property, int $top): array
    {
        $body = $this->get($run, $this->eventsPath($config), $config, [
            '$orderby' => "{$property} desc",
            '$top' => max(1, min(100, $top)),
            '$select' => self::EVENT_FIELDS,
        ]);

        return $this->eventList($body);
    }

    /**
     * The events of a calendar that start within a window, soonest first.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    protected function eventsBetween(Run $run, array $config, CarbonImmutable $from, CarbonImmutable $until, int $top): array
    {
        $path = filled($config['calendar_id'] ?? null) ? '/me/calendars/'.$this->calendarId($config).'/calendarView' : '/me/calendarView';

        return $this->eventList($this->get($run, $path, $config, [
            'startDateTime' => $from->utc()->toIso8601String(),
            'endDateTime' => $until->utc()->toIso8601String(),
            '$orderby' => 'start/dateTime',
            '$top' => max(1, min(100, $top)),
            '$select' => self::EVENT_FIELDS,
        ]));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function eventsPath(array $config): string
    {
        return filled($config['calendar_id'] ?? null) ? '/me/calendars/'.$this->calendarId($config).'/events' : '/me/events';
    }

    /**
     * The calendar id for the request path: only the characters Graph uses in
     * one, so nothing else can be put into the path.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    private function calendarId(array $config): string
    {
        $id = trim((string) $config['calendar_id']);

        if (preg_match('/^[A-Za-z0-9_=-]+$/', $id) !== 1) {
            throw new InvalidArgumentException('Outlook: the calendar is not a valid calendar id.');
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function eventList(array $body): array
    {
        return array_values(array_filter((array) ($body['value'] ?? []), fn (mixed $event): bool => is_array($event) && isset($event['id'])));
    }

    /**
     * When the event starts (the node asks Graph for UTC), or null for an
     * all-day event or one without a time.
     *
     * @param  array<string, mixed>  $event
     */
    protected function eventStart(array $event): ?CarbonImmutable
    {
        $start = $event['start']['dateTime'] ?? null;

        if (! is_string($start) || $start === '' || ($event['isAllDay'] ?? false) === true) {
            return null;
        }

        return CarbonImmutable::parse($start, 'UTC');
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function isCancelled(array $event): bool
    {
        return ($event['isCancelled'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function isDeclined(array $event): bool
    {
        return ($event['responseStatus']['response'] ?? null) === 'declined';
    }
}
