<?php

namespace App\Nodes\Integrations\Salesforce;

class SalesforceListNewCasesNode extends AbstractSalesforceRecordTriggerNode
{
    public function type(): string
    {
        return 'salesforce_list_new_cases';
    }

    public function name(): string
    {
        return 'Salesforce: List New Cases';
    }

    public function description(): string
    {
        return 'Lists the cases created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'Case';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Salesforce: New case';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a case is created in Salesforce. Checks every few minutes.';
    }
}
