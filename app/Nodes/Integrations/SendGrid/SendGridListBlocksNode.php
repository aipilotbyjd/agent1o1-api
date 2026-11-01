<?php

namespace App\Nodes\Integrations\SendGrid;

class SendGridListBlocksNode extends AbstractSendGridSuppressionNode
{
    public function type(): string
    {
        return 'sendgrid_list_blocks';
    }

    public function name(): string
    {
        return 'SendGrid: List Blocks';
    }

    public function description(): string
    {
        return 'Lists the addresses whose mail was blocked by the receiving server, newest first. As a trigger it starts the flow for each new one. Checks every few minutes.';
    }

    protected function group(): string
    {
        return 'blocks';
    }

    protected function resultKey(): string
    {
        return 'blocks';
    }

    public function triggerName(): string
    {
        return 'SendGrid: New block';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when mail to an address is blocked. Checks every few minutes.';
    }
}
