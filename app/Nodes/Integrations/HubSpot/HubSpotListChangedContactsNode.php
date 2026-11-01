<?php

namespace App\Nodes\Integrations\HubSpot;

class HubSpotListChangedContactsNode extends AbstractHubSpotRecordTriggerNode
{
    public function type(): string
    {
        return 'hubspot_list_changed_contacts';
    }

    public function name(): string
    {
        return 'HubSpot: List Changed Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts changed lately, most recent first (a contact changed twice appears as two events). As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'contacts';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'HubSpot: Contact changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is changed in HubSpot, or created. Checks every few minutes.';
    }
}
