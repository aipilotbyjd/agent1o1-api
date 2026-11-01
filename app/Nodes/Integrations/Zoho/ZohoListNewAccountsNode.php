<?php

namespace App\Nodes\Integrations\Zoho;

class ZohoListNewAccountsNode extends AbstractZohoRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_list_new_accounts';
    }

    public function name(): string
    {
        return 'Zoho CRM: List New Accounts';
    }

    public function description(): string
    {
        return 'Lists the accounts created lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function module(): string
    {
        return 'Accounts';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Zoho CRM: New account';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an account is created in Zoho CRM. Instant, with a check every few minutes.';
    }
}
