<?php

namespace App\Nodes\Integrations\Asana;

class AsanaListNewTasksNode extends AbstractAsanaTaskTriggerNode
{
    public function type(): string
    {
        return 'asana_list_new_tasks';
    }

    public function name(): string
    {
        return 'Asana: List New Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks created lately in a project, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function kind(): string
    {
        return 'created';
    }

    public function triggerName(): string
    {
        return 'Asana: New task';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a task is created in a project. Instant, with a check every few minutes.';
    }
}
