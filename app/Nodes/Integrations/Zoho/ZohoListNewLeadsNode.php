<?php

namespace App\Nodes\Integrations\Zoho;

class ZohoListNewLeadsNode extends AbstractZohoRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_list_new_leads';
    }

    public function name(): string
    {
        return 'Zoho CRM: List New Leads';
    }

    public function description(): string
    {
        return 'Lists the leads created lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function module(): string
    {
        return 'Leads';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Zoho CRM: New lead';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a lead is created in Zoho CRM. Instant, with a check every few minutes.';
    }
}
