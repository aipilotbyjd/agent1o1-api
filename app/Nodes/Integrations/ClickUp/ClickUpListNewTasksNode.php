<?php

namespace App\Nodes\Integrations\ClickUp;

class ClickUpListNewTasksNode extends AbstractClickUpTaskTriggerNode
{
    public function type(): string
    {
        return 'clickup_list_new_tasks';
    }

    public function name(): string
    {
        return 'ClickUp: List New Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks created lately in a list, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'ClickUp: New task';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a task is created in a list. Instant, with a check every few minutes.';
    }
}
