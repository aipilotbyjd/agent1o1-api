<?php

namespace App\Nodes\Integrations\Outlook;

use App\Contracts\SchedulesTriggerPolls;
use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Integrations\Outlook\Concerns\ReadsOutlookEvents;
use App\Nodes\Support\Field;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class OutlookListUpcomingEventsNode extends AbstractOutlookNode implements SchedulesTriggerPolls, SupportsTriggerMode
{
    use ActsAsTrigger, ReadsOutlookEvents;

    public function type(): string
    {
        return 'outlook_list_upcoming_events';
    }

    public function name(): string
    {
        return 'Outlook: List Upcoming Events';
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
                'calendar_id' => $this->calendarField(),
                'minutes_before' => Field::integer('Minutes before the start', 'How long before an event begins the flow starts.', 15, 1, 1440),
                'skip_all_day' => Field::boolean('Skip all-day events', 'They have no start time to count down to.', true),
                'skip_declined' => Field::boolean('Skip events you declined', null, true),
                'max_results' => Field::advanced(Field::integer('Max results', null, 50, 1, 100)),
            ],
        ];
    }

    /**
     * How far ahead a read looks for the next event to wake up for.
     */
    private const int LOOKAHEAD_HOURS = 24;

    /**
     * An event that began this recently still counts as "starting": the poll
     * that wakes for it can be up to a minute late.
     */
    private const int LATE_GRACE_SECONDS = 120;

    public function execute(Run $run, array $config, array $context): array
    {
        $now = CarbonImmutable::now();
        $events = $this->eventsBetween($run, $config, $now->subSeconds(self::LATE_GRACE_SECONDS), $now->addHours(self::LOOKAHEAD_HOURS), (int) ($config['max_results'] ?? 50));

        return ['events' => array_values(array_filter($events, fn (array $event): bool => ! $this->isCancelled($event)
            && ! (($config['skip_declined'] ?? true) && $this->isDeclined($event))
            && ! (($config['skip_all_day'] ?? true) && ($event['isAllDay'] ?? false) === true)))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'events';
    }

    /**
     * The events inside the lead time right now.
     */
    public function triggerItems(array $output, array $config): array
    {
        $now = CarbonImmutable::now();
        $lead = $this->leadSeconds($config);

        return array_values(array_filter((array) ($output['events'] ?? []), function (mixed $event) use ($now, $lead): bool {
            $start = is_array($event) ? $this->eventStart($event) : null;

            return $start !== null && $start->lte($now->addSeconds($lead)) && $start->gte($now->subSeconds(self::LATE_GRACE_SECONDS));
        }));
    }

    /**
     * Each occurrence of an event once, and again if it is moved.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['id']) ? $item['id'].':'.($item['start']['dateTime'] ?? '') : '';
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
        $next = null;

        foreach ((array) ($output['events'] ?? []) as $event) {
            $start = is_array($event) ? $this->eventStart($event) : null;

            if ($start === null) {
                continue;
            }

            $wakeAt = $start->subSeconds($this->leadSeconds($config));

            if ($wakeAt->gt($now) && ($next === null || $wakeAt->lt($next))) {
                $next = $wakeAt;
            }
        }

        return $next;
    }

    /**
     * Read as many events as the node can.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 100];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function leadSeconds(array $config): int
    {
        return max(1, (int) ($config['minutes_before'] ?? 15)) * 60;
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Outlook: Event starting soon';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow a set number of minutes before an event begins.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }

    /**
     * Newest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
