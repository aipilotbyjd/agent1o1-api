<?php

namespace App\Nodes\Integrations\Zoho;

class ZohoListChangedDealsNode extends AbstractZohoRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_list_changed_deals';
    }

    public function name(): string
    {
        return 'Zoho CRM: List Changed Deals';
    }

    public function description(): string
    {
        return 'Lists the deals changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function module(): string
    {
        return 'Deals';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Zoho CRM: Deal changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a deal is changed in Zoho CRM, or created. Instant, with a check every few minutes.';
    }
}
