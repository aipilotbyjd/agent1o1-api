<?php

namespace App\Nodes\TriggersEvents;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Enums\Triggers\TriggerNodeType;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Workflows\ConditionEvaluator;

/**
 * Base for the dedicated trigger nodes: where a workflow starts.
 *
 * A trigger node has no inputs. Its output is whatever started the run: the
 * event's payload is the run input (TargetRunStarter), so later steps read
 * it as `{{ nodes.<key>.payload }}`.
 *
 * The node doesn't fire itself. Publishing turns it into a `triggers` row
 * (TriggerNodeSync) that the scheduler, poller and webhook router act on.
 */
abstract class TriggerNode implements DeclaresEffect, DeclaresOutputSchema, HasIcon, NodeContract
{
    /**
     * Which trigger node this is.
     */
    abstract public function nodeType(): TriggerNodeType;

    /**
     * The `workflow_nodes.type` value.
     */
    public function type(): string
    {
        return $this->nodeType()->value;
    }

    /**
     * Every trigger node lives in Triggers & Events.
     */
    public function category(): string
    {
        return NodeCategory::TriggersEvents->value;
    }

    /**
     * Starting a run changes nothing outside the app.
     */
    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    /**
     * What later steps can read from the trigger.
     */
    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'payload' => ['title' => 'Payload', 'description' => 'What started the run: the webhook body, the polled item, or the run input.'],
                'source' => ['title' => 'Source', 'description' => 'How the run started: manual, schedule, webhook or polling.'],
                'fired_at' => ['title' => 'Fired at', 'description' => 'When the run started (ISO 8601).'],
            ],
        ];
    }

    /**
     * Pass on what started the run.
     */
    public function execute(Run $run, array $config, array $context): array
    {
        return [
            'payload' => $context['input'] ?? [],
            'source' => $run->trigger_type ?? $this->nodeType()->triggerType()->value,
            'fired_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Add the settings every dedicated trigger shares to a node's schema.
     *
     * - `if_already_running`: start another run, or skip the event, while
     *   the last run is still going.
     * - `max_failures`: failures in a row before the trigger switches itself
     *   off.
     * - `max_runs_per_minute`: a cap on how fast events start runs.
     * - `batch_seconds`: collect events for this long and run them as one.
     * - `debounce_seconds`: hold events until none has arrived for this long,
     *   then run them as one.
     * - `storm_limit`: events per minute above which the trigger switches
     *   itself off.
     * - `max_concurrent_runs`: how many of its runs may execute at once; the
     *   rest of a burst waits in the queue.
     * - `activate`: the "Activate as flow trigger" switch. The editor draws
     *   it on the node, so it's hidden here. Publishing creates the trigger
     *   on or off to match.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function withActivateSwitch(array $schema): array
    {
        [$minFailures, $maxFailures] = config('triggers.max_failures_range');

        $schema['properties'] += [
            'if_already_running' => Field::advanced(Field::select('If the last run is still going', [
                'queue' => 'Start another run',
                'skip' => 'Skip this event',
            ], 'Skipping suits frequent schedules whose runs can overlap.', 'queue')),
            'max_failures' => Field::advanced(Field::integer('Switch off after failures in a row', 'Defaults to '.config('triggers.failures_before_disable').'.', null, (int) $minFailures, (int) $maxFailures)),
            'max_runs_per_minute' => Field::advanced(Field::integer('Most runs per minute', 'Extra events wait for the next minute instead of starting together. Leave empty for no limit.', null, 1, 600)),
            'batch_seconds' => Field::advanced(Field::integer('Group events for (seconds)', 'Collect events for this long, then run once with all of them as a list. Leave empty to run for each.', null, 5, 3600)),
            'debounce_seconds' => Field::advanced(Field::integer('Wait for quiet (seconds)', 'Hold events until none has arrived for this long, then run once with all of them (the newest is also in "latest"). Use it for bursts, such as one edit saved many times. Not together with "Group events". Checked about once a minute.', null, 5, 3600)),
            'storm_limit' => Field::advanced(Field::integer('Switch off above (events per minute)', 'If more events than this arrive in a minute the trigger switches itself off and tells you, so a loop or bulk import can\'t start thousands of runs. Defaults to '.config('triggers.storm.max_events_per_minute').'. Not applied when events are grouped or waiting for quiet.', null, 10, 10000)),
            'max_concurrent_runs' => Field::advanced(Field::integer('Most runs at once', 'More events wait their turn instead of starting together. Leave empty for no limit.', null, 1, 100)),
            'activate' => Field::hidden('Whether publishing switches this trigger on. Defaults to on.', 'boolean'),
        ];

        return $schema;
    }

    /**
     * The "Only run when" conditions field for event-driven triggers.
     *
     * @see TriggerService::matchesConfiguredFilters()
     *
     * @return array<string, mixed>
     */
    protected function filtersField(): array
    {
        return Field::advanced(Field::json(
            'Only run when',
            'A list of conditions that must all match, e.g. [{"path": "action", "operator": "equals", "value": "opened"}]. Add "source": "header" to check a request header instead of the body. Operators: '.implode(', ', array_keys(ConditionEvaluator::OPERATORS)).'.',
            'array',
        ));
    }
}
