<?php

namespace App\Nodes\Integrations\ClickUp;

class ClickUpListUpdatedTasksNode extends AbstractClickUpTaskTriggerNode
{
    public function type(): string
    {
        return 'clickup_list_updated_tasks';
    }

    public function name(): string
    {
        return 'ClickUp: List Changed Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks changed lately in a list, most recent first (a task changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'ClickUp: Task changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a task in a list is changed in any way, including its status, assignees, comments and due date. Instant, with a check every few minutes.';
    }
}
