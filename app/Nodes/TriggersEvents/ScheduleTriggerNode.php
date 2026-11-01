<?php

namespace App\Nodes\TriggersEvents;

use App\Enums\Triggers\TriggerNodeType;
use App\Nodes\Support\Field;

/**
 * Starts a workflow on a cron schedule, or once at a set time, in the chosen timezone.
 */
class ScheduleTriggerNode extends TriggerNode
{
    /**
     * Which trigger node this is.
     */
    public function nodeType(): TriggerNodeType
    {
        return TriggerNodeType::Schedule;
    }

    /**
     * The name shown in the node library.
     */
    public function name(): string
    {
        return 'Schedule';
    }

    /**
     * The Hugeicons name shown on the node.
     */
    public function icon(): string
    {
        return 'calendar-03';
    }

    /**
     * One line on what the node does.
     */
    public function description(): string
    {
        return 'Starts the workflow on a repeating schedule, or once at a set time.';
    }

    /**
     * The node's settings.
     */
    public function configSchema(): array
    {
        return $this->withActivateSwitch([
            'type' => 'object',
            'required' => [],
            'properties' => [
                'cron' => Field::text('Schedule (cron)', 'Five fields: minute hour day month weekday. "0 9 * * 1-5" is weekdays at 9:00.', '0 9 * * *'),
                'run_once_at' => Field::datetime('Run once at', 'Runs a single time at this date and time, then switches the trigger off. Leave the schedule empty when using this. A time with no offset is read in the timezone below.'),
                'timezone' => Field::dynamic('Timezone', 'system.timezones', 'The schedule runs in this timezone. Leave empty to use the workspace timezone.', 'Workspace timezone', allowCustom: false),
            ],
        ]);
    }
}
