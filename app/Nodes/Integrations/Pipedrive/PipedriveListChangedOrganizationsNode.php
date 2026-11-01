<?php

namespace App\Nodes\Integrations\Pipedrive;

class PipedriveListChangedOrganizationsNode extends AbstractPipedriveRecordTriggerNode
{
    public function type(): string
    {
        return 'pipedrive_list_changed_organizations';
    }

    public function name(): string
    {
        return 'Pipedrive: List Changed Organizations';
    }

    public function description(): string
    {
        return 'Lists the organizations changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function collection(): string
    {
        return 'organizations';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Pipedrive: Organization changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an organization is changed in Pipedrive, or added. Instant, with a check every few minutes.';
    }
}
