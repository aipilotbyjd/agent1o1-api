<?php

namespace App\Nodes\Integrations\Salesforce;

class SalesforceListChangedCasesNode extends AbstractSalesforceRecordTriggerNode
{
    public function type(): string
    {
        return 'salesforce_list_changed_cases';
    }

    public function name(): string
    {
        return 'Salesforce: List Changed Cases';
    }

    public function description(): string
    {
        return 'Lists the cases changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'Case';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Salesforce: Case changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a case is changed in Salesforce, or created. Checks every few minutes.';
    }
}
