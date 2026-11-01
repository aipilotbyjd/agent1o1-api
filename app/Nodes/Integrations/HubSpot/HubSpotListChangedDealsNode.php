<?php

namespace App\Nodes\Integrations\HubSpot;

class HubSpotListChangedDealsNode extends AbstractHubSpotRecordTriggerNode
{
    public function type(): string
    {
        return 'hubspot_list_changed_deals';
    }

    public function name(): string
    {
        return 'HubSpot: List Changed Deals';
    }

    public function description(): string
    {
        return 'Lists the deals changed lately, most recent first (a deal changed twice appears as two events). As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'deals';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'HubSpot: Deal changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a deal is changed in HubSpot, or created. Checks every few minutes.';
    }
}
