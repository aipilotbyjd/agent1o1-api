<?php

namespace App\Nodes\Integrations\HubSpot;

class HubSpotListNewContactsNode extends AbstractHubSpotRecordTriggerNode
{
    public function type(): string
    {
        return 'hubspot_list_new_contacts';
    }

    public function name(): string
    {
        return 'HubSpot: List New Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'contacts';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'HubSpot: New contact';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is created in HubSpot. Checks every few minutes.';
    }
}
