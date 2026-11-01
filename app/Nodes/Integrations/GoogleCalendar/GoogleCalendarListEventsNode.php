<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Carbon\CarbonImmutable;

class GoogleCalendarListEventsNode extends AbstractGoogleCalendarNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a poll looks for events made since it last ran. Longer than
     * any gap between polls, and an event already seen is never run twice.
     */
    private const int NEW_EVENT_WINDOW_HOURS = 6;

    public function type(): string
    {
        return 'google_calendar_list_events';
    }

    public function name(): string
    {
        return 'Google Calendar: List Events';
    }

    public function description(): string
    {
        return 'Lists upcoming events on a calendar.';
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
                'time_min' => Field::datetime('From', 'Only events ending after this time. Defaults to now.'),
                'max_results' => Field::integer('Max results', null, 10, 1, 2500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listEvents($run, $config);
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'items';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Calendar: New event';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a new event is added to the calendar.';
    }

    /**
     * Read the events changed lately instead of the next ones by start time:
     * an event created for next year is on the far end of the start-time list
     * and would never be seen, but it was changed just now.
     */
    public function triggerReadConfig(array $config): array
    {
        return [
            ...$config,
            'ignore_time_min' => true,
            'single_events' => false,
            'order_by' => 'updated',
            'updated_min' => now()->subHours(self::NEW_EVENT_WINDOW_HOURS)->toRfc3339String(),
            'max_results' => 250,
        ];
    }

    /**
     * Events made within the window: one changed lately but made long ago is
     * not new. An event without a creation time is taken as new.
     */
    public function triggerItems(array $output, array $config): array
    {
        $since = now()->subHours(self::NEW_EVENT_WINDOW_HOURS);

        return array_values(array_filter(
            (array) ($output['items'] ?? []),
            fn (mixed $event): bool => is_array($event)
                && ($event['status'] ?? 'confirmed') !== 'cancelled'
                && (! isset($event['created']) || CarbonImmutable::parse((string) $event['created'])->gte($since)),
        ));
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
