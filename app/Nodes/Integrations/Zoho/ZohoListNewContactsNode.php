<?php

namespace App\Nodes\Integrations\Zoho;

class ZohoListNewContactsNode extends AbstractZohoRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_list_new_contacts';
    }

    public function name(): string
    {
        return 'Zoho CRM: List New Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts created lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function module(): string
    {
        return 'Contacts';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Zoho CRM: New contact';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is created in Zoho CRM. Instant, with a check every few minutes.';
    }
}
