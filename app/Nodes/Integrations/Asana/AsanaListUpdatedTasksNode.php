<?php

namespace App\Nodes\Integrations\Asana;

class AsanaListUpdatedTasksNode extends AbstractAsanaTaskTriggerNode
{
    public function type(): string
    {
        return 'asana_list_updated_tasks';
    }

    public function name(): string
    {
        return 'Asana: List Changed Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks changed lately in a project, most recent first (a task changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function kind(): string
    {
        return 'modified';
    }

    public function triggerName(): string
    {
        return 'Asana: Task changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a task in a project is changed in any way, including being created or completed. Instant, with a check every few minutes.';
    }
}
