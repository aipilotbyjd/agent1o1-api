<?php

namespace App\Nodes\Integrations\ZohoDesk;

class ZohoDeskListCustomerRepliesNode extends AbstractZohoDeskTicketTriggerNode
{
    public function type(): string
    {
        return 'zoho_desk_list_customer_replies';
    }

    public function name(): string
    {
        return 'Zoho Desk: List Customer Replies';
    }

    public function description(): string
    {
        return 'Lists the tickets whose customer wrote back lately, newest first. As a trigger it starts the flow for each reply. Instant, with a check every few minutes.';
    }

    protected function stamp(): string
    {
        return 'customerResponseTime';
    }

    public function triggerName(): string
    {
        return 'Zoho Desk: Customer replied';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a customer writes back on a ticket in Zoho Desk. Instant, with a check every few minutes.';
    }
}
