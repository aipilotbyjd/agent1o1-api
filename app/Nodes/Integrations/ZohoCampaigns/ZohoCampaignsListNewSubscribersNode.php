<?php

namespace App\Nodes\Integrations\ZohoCampaigns;

class ZohoCampaignsListNewSubscribersNode extends AbstractZohoCampaignsSubscriberTriggerNode
{
    public function type(): string
    {
        return 'zoho_campaigns_list_new_subscribers';
    }

    public function name(): string
    {
        return 'Zoho Campaigns: List New Subscribers';
    }

    public function description(): string
    {
        return 'Lists the newest subscribers of a mailing list. As a trigger it starts the flow for each new one. Checked every few minutes.';
    }

    protected function status(): string
    {
        return 'active';
    }

    public function triggerName(): string
    {
        return 'Zoho Campaigns: New subscriber';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone subscribes to a mailing list in Zoho Campaigns. Checked every few minutes.';
    }
}
