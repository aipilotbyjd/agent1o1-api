<?php

namespace App\Nodes\Integrations\Pipedrive;

class PipedriveListNewOrganizationsNode extends AbstractPipedriveRecordTriggerNode
{
    public function type(): string
    {
        return 'pipedrive_list_new_organizations';
    }

    public function name(): string
    {
        return 'Pipedrive: List New Organizations';
    }

    public function description(): string
    {
        return 'Lists the organizations added lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function collection(): string
    {
        return 'organizations';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Pipedrive: New organization';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an organization is added in Pipedrive. Instant, with a check every few minutes.';
    }
}
