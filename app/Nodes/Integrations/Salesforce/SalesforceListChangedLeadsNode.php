<?php

namespace App\Nodes\Integrations\Salesforce;

class SalesforceListChangedLeadsNode extends AbstractSalesforceRecordTriggerNode
{
    public function type(): string
    {
        return 'salesforce_list_changed_leads';
    }

    public function name(): string
    {
        return 'Salesforce: List Changed Leads';
    }

    public function description(): string
    {
        return 'Lists the leads changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'Lead';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Salesforce: Lead changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a lead is changed in Salesforce, or created. Checks every few minutes.';
    }
}
