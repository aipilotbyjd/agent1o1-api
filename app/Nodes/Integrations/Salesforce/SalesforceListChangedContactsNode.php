<?php

namespace App\Nodes\Integrations\Salesforce;

class SalesforceListChangedContactsNode extends AbstractSalesforceRecordTriggerNode
{
    public function type(): string
    {
        return 'salesforce_list_changed_contacts';
    }

    public function name(): string
    {
        return 'Salesforce: List Changed Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'Contact';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Salesforce: Contact changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is changed in Salesforce, or created. Checks every few minutes.';
    }
}
