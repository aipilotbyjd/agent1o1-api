<?php

namespace App\Nodes\Integrations\Zoho;

class ZohoListNewDealsNode extends AbstractZohoRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_list_new_deals';
    }

    public function name(): string
    {
        return 'Zoho CRM: List New Deals';
    }

    public function description(): string
    {
        return 'Lists the deals created lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function module(): string
    {
        return 'Deals';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Zoho CRM: New deal';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a deal is created in Zoho CRM. Instant, with a check every few minutes.';
    }
}
