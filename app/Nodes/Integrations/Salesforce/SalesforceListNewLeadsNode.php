<?php

namespace App\Nodes\Integrations\Salesforce;

class SalesforceListNewLeadsNode extends AbstractSalesforceRecordTriggerNode
{
    public function type(): string
    {
        return 'salesforce_list_new_leads';
    }

    public function name(): string
    {
        return 'Salesforce: List New Leads';
    }

    public function description(): string
    {
        return 'Lists the leads created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'Lead';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Salesforce: New lead';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a lead is created in Salesforce. Checks every few minutes.';
    }
}
