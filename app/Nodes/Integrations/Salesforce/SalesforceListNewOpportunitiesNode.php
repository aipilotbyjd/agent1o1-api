<?php

namespace App\Nodes\Integrations\Salesforce;

class SalesforceListNewOpportunitiesNode extends AbstractSalesforceRecordTriggerNode
{
    public function type(): string
    {
        return 'salesforce_list_new_opportunities';
    }

    public function name(): string
    {
        return 'Salesforce: List New Opportunities';
    }

    public function description(): string
    {
        return 'Lists the opportunities created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'Opportunity';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Salesforce: New opportunity';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an opportunity is created in Salesforce. Checks every few minutes.';
    }
}
