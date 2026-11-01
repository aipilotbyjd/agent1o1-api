<?php

namespace App\Nodes\Integrations\Salesforce;

class SalesforceListChangedOpportunitiesNode extends AbstractSalesforceRecordTriggerNode
{
    public function type(): string
    {
        return 'salesforce_list_changed_opportunities';
    }

    public function name(): string
    {
        return 'Salesforce: List Changed Opportunities';
    }

    public function description(): string
    {
        return 'Lists the opportunities changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'Opportunity';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Salesforce: Opportunity changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an opportunity is changed in Salesforce, or created. Checks every few minutes.';
    }
}
