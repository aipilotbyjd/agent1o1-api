<?php

namespace App\Nodes\Integrations\ZohoCampaigns;

class ZohoCampaignsListUnsubscribesNode extends AbstractZohoCampaignsSubscriberTriggerNode
{
    public function type(): string
    {
        return 'zoho_campaigns_list_unsubscribes';
    }

    public function name(): string
    {
        return 'Zoho Campaigns: List Unsubscribes';
    }

    public function description(): string
    {
        return 'Lists the people who unsubscribed from a mailing list, newest first. As a trigger it starts the flow for each new one. Checked every few minutes.';
    }

    protected function status(): string
    {
        return 'unsub';
    }

    public function triggerName(): string
    {
        return 'Zoho Campaigns: Unsubscribed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone unsubscribes from a mailing list in Zoho Campaigns. Checked every few minutes.';
    }
}
