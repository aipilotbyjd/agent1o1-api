<?php

namespace App\Nodes\Integrations\HubSpot;

class HubSpotListNewCompaniesNode extends AbstractHubSpotRecordTriggerNode
{
    public function type(): string
    {
        return 'hubspot_list_new_companies';
    }

    public function name(): string
    {
        return 'HubSpot: List New Companies';
    }

    public function description(): string
    {
        return 'Lists the companies created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'companies';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'HubSpot: New company';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a company is created in HubSpot. Checks every few minutes.';
    }
}
