<?php

namespace App\Nodes\Integrations\Postmark;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class PostmarkListInboundMessagesNode extends AbstractPostmarkNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'postmark_list_inbound_messages';
    }

    public function name(): string
    {
        return 'Postmark: List Received Emails';
    }

    public function description(): string
    {
        return 'Lists emails received by the server\'s inbound address, newest first. As a trigger it starts the flow for each email that arrives, with its body and attachments.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'from_email' => Field::advanced(Field::text('From address')),
                'subject' => Field::advanced(Field::text('Subject contains')),
                'mailbox_hash' => Field::advanced(Field::text('Mailbox hash', 'The part after + in the address, e.g. support in abc+support@inbound.postmarkapp.com.')),
                'since' => Field::advanced(Field::datetime('Received after')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $messages = $this->listAll($run, $config, '/messages/inbound', 'InboundMessages', [
            'fromemail' => filled($config['from_email'] ?? null) ? trim((string) $config['from_email']) : null,
            'subject' => filled($config['subject'] ?? null) ? trim((string) $config['subject']) : null,
            'mailboxhash' => filled($config['mailbox_hash'] ?? null) ? trim((string) $config['mailbox_hash']) : null,
            'fromdate' => $this->moment($config, 'since'),
        ], $this->limit($config, 50));

        return ['messages' => array_map(fn (array $message): array => [...$this->shapeMessage($message), 'from_name' => $message['FromName'] ?? null, 'mailbox_hash' => $message['MailboxHash'] ?? null], $messages), 'count' => count($messages)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'messages';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Postmark: Email received';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an email arrives at the server\'s inbound address, with its text, HTML and attachments. Instant when the server has no inbound webhook of its own, otherwise a check every few minutes.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'since' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 500];
    }

    /**
     * The list has no bodies: read the message itself.
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        return [...$item, ...$this->inbound((array) $this->postmark($run, $config, 'GET', '/messages/inbound/'.rawurlencode((string) ($item['id'] ?? '')).'/details'))];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
