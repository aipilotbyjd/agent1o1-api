<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoDeskSendReplyNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_send_reply';
    }

    public function name(): string
    {
        return 'Zoho Desk: Send Reply';
    }

    public function description(): string
    {
        return 'Replies to the customer by email from the ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'to', 'from_email', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id', 'An id from a previous step (a long number).'),
                'to' => Field::text('To', 'The customer\'s email.', 'ada@example.com'),
                'from_email' => Field::text('From', 'A support address set up in the department.', 'support@acme.com'),
                'content' => Field::textarea('Reply', 'Plain text or HTML.'),
                'html' => Field::advanced(Field::boolean('The reply is HTML', null, false)),
                'close' => Field::advanced(Field::boolean('Close the ticket after replying', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $reply = $this->desk($run, $config, 'POST', "/tickets/{$id}/sendReply", [], [
            'channel' => 'EMAIL',
            'to' => $this->email($config, 'to'),
            'fromEmailAddress' => $this->email($config, 'from_email'),
            'content' => $this->requiredString($config, 'content', 'The reply'),
            'contentType' => ($config['html'] ?? false) ? 'html' : 'plainText',
            'isForward' => false,
        ]);

        if ($config['close'] ?? false) {
            $this->desk($run, $config, 'PATCH', "/tickets/{$id}", [], ['status' => 'Closed']);
        }

        return ['sent' => true, 'ticket_id' => $id, 'thread_id' => isset($reply['id']) ? (string) $reply['id'] : null, 'closed' => (bool) ($config['close'] ?? false)];
    }
}
