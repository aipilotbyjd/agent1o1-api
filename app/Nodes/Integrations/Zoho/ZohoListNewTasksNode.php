<?php

namespace App\Nodes\Integrations\Zoho;

class ZohoListNewTasksNode extends AbstractZohoRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_list_new_tasks';
    }

    public function name(): string
    {
        return 'Zoho CRM: List New Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks created lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function module(): string
    {
        return 'Tasks';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Zoho CRM: New task';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a task is created in Zoho CRM. Instant, with a check every few minutes.';
    }
}
