<?php

namespace App\Nodes\Integrations\Zoho;

class ZohoListChangedContactsNode extends AbstractZohoRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_list_changed_contacts';
    }

    public function name(): string
    {
        return 'Zoho CRM: List Changed Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function module(): string
    {
        return 'Contacts';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Zoho CRM: Contact changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is changed in Zoho CRM, or created. Instant, with a check every few minutes.';
    }
}
