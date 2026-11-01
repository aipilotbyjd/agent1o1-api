<?php

namespace App\Services\Triggers;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Triggers\TriggerNodeType;
use App\Nodes\Support\Field;
use App\Services\Workflows\NodeRegistry;

/**
 * Decides which nodes on a graph start runs.
 *
 * The dedicated trigger nodes always do. A reader node that supports trigger
 * mode (Gmail, Slack...) does only once its "Activate as flow trigger"
 * switch is on. The engine, the graph validator and TriggerNodeSync all ask
 * here, so they can't disagree.
 */
class TriggerNodes
{
    public function __construct(private readonly NodeRegistry $registry) {}

    /**
     * Whether a graph node starts runs.
     *
     * @param  array{type: string, config?: array<string, mixed>}  $node
     */
    public function isTriggerEntry(array $node): bool
    {
        if (TriggerNodeType::isTriggerNode((string) $node['type'])) {
            return true;
        }

        return $this->supportsTriggerMode((string) $node['type']) && self::isAppTriggerOn($node['config'] ?? []);
    }

    /**
     * Whether a node type can be switched on as a flow trigger.
     */
    public function supportsTriggerMode(string $type): bool
    {
        return $this->registry->has($type) && $this->registry->resolve($type) instanceof SupportsTriggerMode;
    }

    /**
     * Whether a reader node's "Activate as flow trigger" switch is on.
     *
     * Unlike a dedicated trigger node's, it defaults to off: the node is an
     * ordinary step until someone switches it on.
     *
     * @param  array<string, mixed>  $config
     */
    public static function isAppTriggerOn(array $config): bool
    {
        return ($config['activate'] ?? false) === true;
    }

    /**
     * The trigger-mode settings every SupportsTriggerMode node gains on top
     * of its own schema. NodeDescriptor merges them in.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function appTriggerFields(int $defaultPollMinutes): array
    {
        [$minFailures, $maxFailures] = config('triggers.max_failures_range');

        return [
            'activate' => Field::hidden('Activate as flow trigger: publishing then starts a run for each new item.', 'boolean'),
            'poll_interval_minutes' => Field::advanced(Field::integer('Trigger: check every (minutes)', 'Only used when this step is activated as a flow trigger.', $defaultPollMinutes, max(1, (int) config('triggers.min_poll_interval_minutes')), 1440)),
            'if_already_running' => Field::advanced(Field::select('Trigger: if the last run is still going', [
                'queue' => 'Start another run',
                'skip' => 'Skip this item',
            ], null, 'queue')),
            'max_failures' => Field::advanced(Field::integer('Trigger: switch off after failures in a row', null, null, (int) $minFailures, (int) $maxFailures)),
            'start_from' => Field::advanced(Field::select('Trigger: start from', [
                'now' => 'Only items that arrive from now on',
                'beginning' => 'Also the items already there',
            ], 'Decides what the first check does with what is already in the app.', 'now')),
            'max_runs_per_minute' => Field::advanced(Field::integer('Trigger: most runs per minute', 'Extra items wait for the next minute instead of starting together. Leave empty for no limit.', null, 1, 600)),
            'batch_seconds' => Field::advanced(Field::integer('Trigger: group events for (seconds)', 'Collect events for this long, then run once with all of them as a list. Leave empty to run for each.', null, 5, 3600)),
            'debounce_seconds' => Field::advanced(Field::integer('Trigger: wait for quiet (seconds)', 'Hold events until none has arrived for this long, then run once with all of them as a list (the newest is also in "latest"). Not together with "group events". Checked about once a minute.', null, 5, 3600)),
            'storm_limit' => Field::advanced(Field::integer('Trigger: switch off above (events per minute)', 'If more events than this arrive in a minute the trigger switches itself off and tells you. Defaults to '.config('triggers.storm.max_events_per_minute').'. Not applied when events are grouped or waiting for quiet.', null, 10, 10000)),
            'max_concurrent_runs' => Field::advanced(Field::integer('Trigger: most runs at once', 'More items wait their turn instead of starting together. Leave empty for no limit.', null, 1, 100)),
        ];
    }
}
