<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GoogleCalendarListUpdatedEventsNode extends AbstractGoogleCalendarNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * An event edited this soon after it was made is the creation itself, not
     * a change to it.
     */
    private const int CREATION_GRACE_SECONDS = 15;

    /**
     * How far back a poll looks for changes since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'google_calendar_list_updated_events';
    }

    public function name(): string
    {
        return 'Google Calendar: List Updated Events';
    }

    public function description(): string
    {
        return 'Lists events that were changed lately: moved, renamed, or given new guests.';
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
                'changed_within_minutes' => Field::integer('Changed within (minutes)', 'Only events changed this recently.', 60, 1, 43200),
                'max_results' => Field::integer('Max results', null, 50, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listEvents($run, [
            ...$config,
            'ignore_time_min' => true,
            'single_events' => false,
            'order_by' => 'updated',
            'updated_min' => now()->subMinutes((int) ($config['changed_within_minutes'] ?? 60))->toRfc3339String(),
        ]);
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'items';
    }

    /**
     * Events edited after they were made, not cancelled ones and not the act
     * of creating them.
     */
    public function triggerItems(array $output, array $config): array
    {
        return array_values(array_filter(
            (array) ($output['items'] ?? []),
            fn (mixed $event): bool => is_array($event)
                && ($event['status'] ?? 'confirmed') !== 'cancelled'
                && $this->secondsEdited($event) > self::CREATION_GRACE_SECONDS,
        ));
    }

    /**
     * An event is a new item each time it changes: its id and last change.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['id']) ? $item['id'].':'.($item['updated'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Calendar: Event updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an event is changed: moved, renamed, or given new guests.';
    }

    /**
     * Read the changes since the poll last ran.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'max_results' => 250];
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
