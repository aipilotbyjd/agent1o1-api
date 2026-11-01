<?php

namespace App\Nodes\Integrations\Outlook;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Integrations\Outlook\Concerns\ReadsOutlookEvents;
use App\Nodes\Support\Field;

class OutlookListCancelledEventsNode extends AbstractOutlookNode implements SupportsTriggerMode
{
    use ActsAsTrigger, ReadsOutlookEvents;

    public function type(): string
    {
        return 'outlook_list_cancelled_events';
    }

    public function name(): string
    {
        return 'Outlook: List Cancelled Events';
    }

    public function description(): string
    {
        return 'Lists the events of a calendar that were cancelled, most recently changed first.';
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

        return ['events' => array_values(array_filter($events, fn (array $event): bool => $this->isCancelled($event)))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'events';
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
        return 'Outlook: Event cancelled';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an event of the calendar is cancelled.';
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
