<?php

namespace App\Nodes\Integrations\ZohoProjects;

class ZohoProjectsListChangedTasksNode extends AbstractZohoProjectsRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_projects_list_changed_tasks';
    }

    public function name(): string
    {
        return 'Zoho Projects: List Changed Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks changed lately, newest first. As a trigger it starts the flow each time one changes. Checked every few minutes.';
    }

    protected function path(): string
    {
        return '/tasks';
    }

    protected function collection(): string
    {
        return 'tasks';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    protected function shape(array $record): array
    {
        return $this->shapeTask($record);
    }

    public function triggerName(): string
    {
        return 'Zoho Projects: Task updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a task is changed in Zoho Projects. Checked every few minutes.';
    }
}
