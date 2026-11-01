<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GoogleCalendarListCancelledEventsNode extends AbstractGoogleCalendarNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a poll looks for cancellations since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'google_calendar_list_cancelled_events';
    }

    public function name(): string
    {
        return 'Google Calendar: List Cancelled Events';
    }

    public function description(): string
    {
        return 'Lists events that were cancelled or deleted lately.';
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
                'cancelled_within_minutes' => Field::integer('Cancelled within (minutes)', 'Only events cancelled this recently.', 60, 1, 43200),
                'max_results' => Field::integer('Max results', null, 50, 1, 250),
            ],
        ];
    }

    /**
     * Cancelled events are only returned when asked for, along with everything
     * else changed in the window; the cancelled ones are picked out.
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $events = $this->listEvents($run, [
            ...$config,
            'ignore_time_min' => true,
            'single_events' => false,
            'show_deleted' => true,
            'order_by' => 'updated',
            'updated_min' => now()->subMinutes((int) ($config['cancelled_within_minutes'] ?? 60))->toRfc3339String(),
        ]);

        return ['items' => array_values(array_filter(
            (array) ($events['items'] ?? []),
            fn (mixed $event): bool => is_array($event) && ($event['status'] ?? null) === 'cancelled',
        ))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'items';
    }

    /**
     * An event is cancelled once; its id and the time it changed keep a
     * recurring series' cancelled instances apart.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['id']) ? $item['id'].':cancelled:'.($item['updated'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Calendar: Event cancelled';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an event is cancelled or deleted.';
    }

    /**
     * Read the cancellations since the poll last ran.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'cancelled_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'max_results' => 250];
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
