<?php

namespace App\Nodes\Integrations\ZohoProjects;

class ZohoProjectsListNewTasksNode extends AbstractZohoProjectsRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_projects_list_new_tasks';
    }

    public function name(): string
    {
        return 'Zoho Projects: List New Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks added lately, newest first. As a trigger it starts the flow for each one. Checked every few minutes.';
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
        return true;
    }

    protected function shape(array $record): array
    {
        return $this->shapeTask($record);
    }

    public function triggerName(): string
    {
        return 'Zoho Projects: New task';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a task is added in Zoho Projects. Checked every few minutes.';
    }
}
