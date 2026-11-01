<?php

namespace App\Nodes\Integrations\HubSpot;

class HubSpotListChangedCompaniesNode extends AbstractHubSpotRecordTriggerNode
{
    public function type(): string
    {
        return 'hubspot_list_changed_companies';
    }

    public function name(): string
    {
        return 'HubSpot: List Changed Companies';
    }

    public function description(): string
    {
        return 'Lists the companies changed lately, most recent first (a company changed twice appears as two events). As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'companies';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'HubSpot: Company changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a company is changed in HubSpot, or created. Checks every few minutes.';
    }
}
