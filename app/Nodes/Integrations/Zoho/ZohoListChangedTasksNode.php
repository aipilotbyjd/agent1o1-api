<?php

namespace App\Nodes\Integrations\Zoho;

class ZohoListChangedTasksNode extends AbstractZohoRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_list_changed_tasks';
    }

    public function name(): string
    {
        return 'Zoho CRM: List Changed Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function module(): string
    {
        return 'Tasks';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Zoho CRM: Task changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a task is changed in Zoho CRM, or created. Instant, with a check every few minutes.';
    }
}
