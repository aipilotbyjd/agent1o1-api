<?php

namespace App\Nodes\Integrations\SendGrid;

class SendGridListUnsubscribesNode extends AbstractSendGridSuppressionNode
{
    public function type(): string
    {
        return 'sendgrid_list_unsubscribes';
    }

    public function name(): string
    {
        return 'SendGrid: List Global Unsubscribes';
    }

    public function description(): string
    {
        return 'Lists the addresses that unsubscribed from all your email, newest first. As a trigger it starts the flow for each new one. Checks every few minutes.';
    }

    protected function group(): string
    {
        return 'unsubscribes';
    }

    protected function resultKey(): string
    {
        return 'unsubscribes';
    }

    public function triggerName(): string
    {
        return 'SendGrid: New unsubscribe';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone unsubscribes from all your email. Checks every few minutes.';
    }
}
