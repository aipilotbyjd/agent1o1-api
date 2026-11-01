<?php

namespace App\Nodes\Integrations\SendGrid;

class SendGridListSpamReportsNode extends AbstractSendGridSuppressionNode
{
    public function type(): string
    {
        return 'sendgrid_list_spam_reports';
    }

    public function name(): string
    {
        return 'SendGrid: List Spam Reports';
    }

    public function description(): string
    {
        return 'Lists the people who marked your email as spam, newest first. As a trigger it starts the flow for each new one. Checks every few minutes.';
    }

    protected function group(): string
    {
        return 'spam_reports';
    }

    protected function resultKey(): string
    {
        return 'spam_reports';
    }

    public function triggerName(): string
    {
        return 'SendGrid: New spam report';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone marks your email as spam. Checks every few minutes.';
    }
}
