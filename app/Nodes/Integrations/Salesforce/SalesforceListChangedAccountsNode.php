<?php

namespace App\Nodes\Integrations\Salesforce;

class SalesforceListChangedAccountsNode extends AbstractSalesforceRecordTriggerNode
{
    public function type(): string
    {
        return 'salesforce_list_changed_accounts';
    }

    public function name(): string
    {
        return 'Salesforce: List Changed Accounts';
    }

    public function description(): string
    {
        return 'Lists the accounts changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'Account';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Salesforce: Account changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an account is changed in Salesforce, or created. Checks every few minutes.';
    }
}
