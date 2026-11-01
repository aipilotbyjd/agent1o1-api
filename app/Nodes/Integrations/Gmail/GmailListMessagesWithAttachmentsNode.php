<?php

namespace App\Nodes\Integrations\Gmail;

use App\Models\Runs\Run;

/**
 * Messages that carry a file. The same search as List Messages with
 * `has:attachment` added, so a flow can start on invoices, reports and
 * receipts and nothing else.
 */
class GmailListMessagesWithAttachmentsNode extends GmailListMessagesNode
{
    public function type(): string
    {
        return 'gmail_list_messages_with_attachments';
    }

    public function name(): string
    {
        return 'Gmail: List Messages With Attachments';
    }

    public function description(): string
    {
        return 'Lists messages that have a file attached, matching a query.';
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return parent::execute($run, [...$config, 'query' => trim('has:attachment '.($config['query'] ?? ''))], $context);
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Gmail: New email with attachment';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an email with a file attached arrives, with the files listed.';
    }
}
