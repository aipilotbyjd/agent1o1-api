<?php

namespace App\Nodes\Integrations\ZohoProjects;

class ZohoProjectsListNewBugsNode extends AbstractZohoProjectsRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_projects_list_new_bugs';
    }

    public function name(): string
    {
        return 'Zoho Projects: List New Bugs';
    }

    public function description(): string
    {
        return 'Lists the bugs reported lately, newest first. As a trigger it starts the flow for each one. Checked every few minutes.';
    }

    protected function path(): string
    {
        return '/issues';
    }

    protected function collection(): string
    {
        return 'bugs';
    }

    protected function answerKey(): string
    {
        return 'issues';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    protected function shape(array $record): array
    {
        return $this->shapeBug($record);
    }

    public function triggerName(): string
    {
        return 'Zoho Projects: New bug';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a bug is reported in Zoho Projects. Checked every few minutes.';
    }
}
