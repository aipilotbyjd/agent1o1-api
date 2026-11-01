<?php

namespace App\Nodes\Integrations\SendGrid;

class SendGridListBouncesNode extends AbstractSendGridSuppressionNode
{
    public function type(): string
    {
        return 'sendgrid_list_bounces';
    }

    public function name(): string
    {
        return 'SendGrid: List Bounces';
    }

    public function description(): string
    {
        return 'Lists the addresses that bounced, newest first. As a trigger it starts the flow for each new one. Checks every few minutes.';
    }

    protected function group(): string
    {
        return 'bounces';
    }

    protected function resultKey(): string
    {
        return 'bounces';
    }

    public function triggerName(): string
    {
        return 'SendGrid: New bounce';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an email to an address bounces. Checks every few minutes.';
    }
}
