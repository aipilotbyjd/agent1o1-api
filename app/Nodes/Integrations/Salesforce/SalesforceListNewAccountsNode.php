<?php

namespace App\Nodes\Integrations\Salesforce;

class SalesforceListNewAccountsNode extends AbstractSalesforceRecordTriggerNode
{
    public function type(): string
    {
        return 'salesforce_list_new_accounts';
    }

    public function name(): string
    {
        return 'Salesforce: List New Accounts';
    }

    public function description(): string
    {
        return 'Lists the accounts created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'Account';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Salesforce: New account';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an account is created in Salesforce. Checks every few minutes.';
    }
}
