<?php

namespace App\Nodes\Integrations\Zoho;

class ZohoListChangedLeadsNode extends AbstractZohoRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_list_changed_leads';
    }

    public function name(): string
    {
        return 'Zoho CRM: List Changed Leads';
    }

    public function description(): string
    {
        return 'Lists the leads changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function module(): string
    {
        return 'Leads';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Zoho CRM: Lead changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a lead is changed in Zoho CRM, or created. Instant, with a check every few minutes.';
    }
}
