<?php

namespace App\Nodes\Integrations\HubSpot;

class HubSpotListNewDealsNode extends AbstractHubSpotRecordTriggerNode
{
    public function type(): string
    {
        return 'hubspot_list_new_deals';
    }

    public function name(): string
    {
        return 'HubSpot: List New Deals';
    }

    public function description(): string
    {
        return 'Lists the deals created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'deals';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'HubSpot: New deal';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a deal is created in HubSpot. Checks every few minutes.';
    }
}
