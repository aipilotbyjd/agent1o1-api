<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Contracts\SchedulesTriggerPolls;
use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * "Event starting soon": starts a flow N minutes before an event begins.
 *
 * It does not poll every minute to catch the moment. After each read it tells
 * the poller when the next event comes into its window (SchedulesTriggerPolls),
 * so a calendar with a meeting at 15:00 and a 10 minute lead is looked at once
 * around 14:50 and not 600 times before it.
 */
class GoogleCalendarListUpcomingEventsNode extends AbstractGoogleCalendarNode implements SchedulesTriggerPolls, SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far ahead a read looks for the next event to wake up for.
     */
    private const int LOOKAHEAD_HOURS = 24;

    /**
     * An event that began this recently still counts as "starting": the poll
     * that wakes for it can be up to a minute late.
     */
    private const int LATE_GRACE_SECONDS = 120;

    public function type(): string
    {
        return 'google_calendar_list_upcoming_events';
    }

    public function name(): string
    {
        return 'Google Calendar: List Upcoming Events';
    }

    public function description(): string
    {
        return 'Lists the events starting in the next day, soonest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'calendar_id' => Field::dynamic('Calendar', 'google_calendar.calendars', 'Defaults to your primary calendar.', 'primary'),
                'minutes_before' => Field::integer('Minutes before the start', 'How long before an event begins the flow starts.', 15, 1, 1440),
                'skip_all_day' => Field::boolean('Skip all-day events', 'They have no start time to count down to.', true),
                'skip_declined' => Field::boolean('Skip events you declined', null, true),
                'max_results' => Field::integer('Max results', null, 50, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $now = now();

        $response = $this->listEvents($run, [
            ...$config,
            'time_min' => $now->copy()->subSeconds(self::LATE_GRACE_SECONDS)->toRfc3339String(),
            'time_max' => $now->copy()->addHours(self::LOOKAHEAD_HOURS)->toRfc3339String(),
            'single_events' => true,
            'order_by' => 'startTime',
        ]);

        return ['items' => $this->wanted((array) ($response['items'] ?? []), $config)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'items';
    }

    /**
     * The events inside the lead time right now.
     */
    public function triggerItems(array $output, array $config): array
    {
        $now = CarbonImmutable::now();
        $leadSeconds = $this->leadMinutes($config) * 60;

        return array_values(array_filter(
            (array) ($output['items'] ?? []),
            function (array $event) use ($now, $leadSeconds): bool {
                $start = $this->eventStart($event);

                return $start !== null
                    && $start->lte($now->addSeconds($leadSeconds))
                    && $start->gte($now->subSeconds(self::LATE_GRACE_SECONDS));
            },
        ));
    }

    /**
     * Each occurrence of an event once, and again if it is moved.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['id']) ? $item['id'].':'.($item['start']['dateTime'] ?? $item['start']['date'] ?? '') : '';
    }

    /**
     * The event, with how long until it starts.
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        $start = $this->eventStart($item);

        return [...$item, 'minutes_until_start' => $start === null ? null : max(0, (int) round(CarbonImmutable::now()->diffInSeconds($start, false) / 60))];
    }

    /**
     * The moment the next event not yet in its lead time gets there.
     */
    public function nextTriggerPollAt(array $output, array $config): ?CarbonInterface
    {
        $now = CarbonImmutable::now();
        $lead = $this->leadMinutes($config);
        $next = null;

        foreach ((array) ($output['items'] ?? []) as $event) {
            $start = is_array($event) ? $this->eventStart($event) : null;

            if ($start === null) {
                continue;
            }

            $wakeAt = $start->subMinutes($lead);

            if ($wakeAt->gt($now) && ($next === null || $wakeAt->lt($next))) {
                $next = $wakeAt;
            }
        }

        return $next;
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Calendar: Event starting soon';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow a set number of minutes before an event begins.';
    }

    /**
     * Read as many events as the node can.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 250];
    }

    /**
     * How often to look for events added since: a new meeting can appear at
     * any time, and a push event from Google brings this forward.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function leadMinutes(array $config): int
    {
        return max(1, (int) ($config['minutes_before'] ?? 15));
    }

    /**
     * The events worth waking for: not cancelled, with a start, and (as
     * asked) not all-day and not declined.
     *
     * @param  list<mixed>  $events
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    private function wanted(array $events, array $config): array
    {
        return array_values(array_filter($events, function (mixed $event) use ($config): bool {
            if (! is_array($event) || ($event['status'] ?? 'confirmed') === 'cancelled') {
                return false;
            }

            if (($config['skip_all_day'] ?? true) && ! isset($event['start']['dateTime'])) {
                return false;
            }

            if ($config['skip_declined'] ?? true) {
                foreach ((array) ($event['attendees'] ?? []) as $attendee) {
                    if (($attendee['self'] ?? false) && ($attendee['responseStatus'] ?? null) === 'declined') {
                        return false;
                    }
                }
            }

            return $this->eventStart($event) !== null;
        }));
    }
}
