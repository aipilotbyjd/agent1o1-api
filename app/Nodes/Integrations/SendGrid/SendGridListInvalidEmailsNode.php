<?php

namespace App\Nodes\Integrations\SendGrid;

class SendGridListInvalidEmailsNode extends AbstractSendGridSuppressionNode
{
    public function type(): string
    {
        return 'sendgrid_list_invalid_emails';
    }

    public function name(): string
    {
        return 'SendGrid: List Invalid Emails';
    }

    public function description(): string
    {
        return 'Lists the addresses SendGrid found invalid, newest first. As a trigger it starts the flow for each new one. Checks every few minutes.';
    }

    protected function group(): string
    {
        return 'invalid_emails';
    }

    protected function resultKey(): string
    {
        return 'invalid_emails';
    }

    public function triggerName(): string
    {
        return 'SendGrid: New invalid email';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an address turns out to be invalid. Checks every few minutes.';
    }
}
