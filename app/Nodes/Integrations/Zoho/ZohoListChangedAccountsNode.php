<?php

namespace App\Nodes\Integrations\Zoho;

class ZohoListChangedAccountsNode extends AbstractZohoRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_list_changed_accounts';
    }

    public function name(): string
    {
        return 'Zoho CRM: List Changed Accounts';
    }

    public function description(): string
    {
        return 'Lists the accounts changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function module(): string
    {
        return 'Accounts';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Zoho CRM: Account changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an account is changed in Zoho CRM, or created. Instant, with a check every few minutes.';
    }
}
