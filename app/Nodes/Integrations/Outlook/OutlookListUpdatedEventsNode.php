<?php

namespace App\Nodes\Integrations\Outlook;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Integrations\Outlook\Concerns\ReadsOutlookEvents;
use App\Nodes\Support\Field;

class OutlookListUpdatedEventsNode extends AbstractOutlookNode implements SupportsTriggerMode
{
    use ActsAsTrigger, ReadsOutlookEvents;

    public function type(): string
    {
        return 'outlook_list_updated_events';
    }

    public function name(): string
    {
        return 'Outlook: List Updated Events';
    }

    public function description(): string
    {
        return 'Lists the most recently changed events of a calendar.';
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
                'calendar_id' => $this->calendarField(),
                'max_results' => Field::advanced(Field::integer('Max results', null, 50, 1, 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $events = $this->eventsOrderedBy($run, $config, 'lastModifiedDateTime', (int) ($config['max_results'] ?? 50));

        return ['events' => array_values(array_filter($events, fn (array $event): bool => ! $this->isCancelled($event)))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'events';
    }

    /**
     * An event is named by its id and the time it last changed: each change is a new event.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['id'] ?? '').':'.($item['lastModifiedDateTime'] ?? '');
    }

    /**
     * Read the widest page, so a burst between two polls isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 100];
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Outlook: Event updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an event of the calendar is added or changed.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * Newest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
