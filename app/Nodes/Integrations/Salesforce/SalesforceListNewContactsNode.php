<?php

namespace App\Nodes\Integrations\Salesforce;

class SalesforceListNewContactsNode extends AbstractSalesforceRecordTriggerNode
{
    public function type(): string
    {
        return 'salesforce_list_new_contacts';
    }

    public function name(): string
    {
        return 'Salesforce: List New Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'Contact';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Salesforce: New contact';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is created in Salesforce. Checks every few minutes.';
    }
}
