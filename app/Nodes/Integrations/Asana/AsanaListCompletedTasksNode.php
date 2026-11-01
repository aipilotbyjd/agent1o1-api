<?php

namespace App\Nodes\Integrations\Asana;

class AsanaListCompletedTasksNode extends AbstractAsanaTaskTriggerNode
{
    public function type(): string
    {
        return 'asana_list_completed_tasks';
    }

    public function name(): string
    {
        return 'Asana: List Completed Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks completed lately in a project, most recent first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function kind(): string
    {
        return 'completed';
    }

    public function triggerName(): string
    {
        return 'Asana: Task completed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a task in a project is completed. Instant, with a check every few minutes.';
    }
}
