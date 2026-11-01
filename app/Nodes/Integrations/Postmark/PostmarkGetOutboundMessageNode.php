<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PostmarkGetOutboundMessageNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_get_outbound_message';
    }

    public function name(): string
    {
        return 'Postmark: Get Sent Message';
    }

    public function description(): string
    {
        return 'Reads one sent email with its recipients, status and every event (delivered, opened, clicked...).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::text('Message id', 'The id Send Email returned.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $message = (array) $this->postmark($run, $config, 'GET', '/messages/outbound/'.$this->pathPart($config, 'message_id', 'The message id').'/details');

        return [
            ...$this->shapeMessage($message),
            'events' => array_values(array_map(fn (array $event): array => ['type' => $event['Type'] ?? null, 'at' => $event['ReceivedAt'] ?? null, 'recipient' => $event['Recipient'] ?? null, 'details' => $event['Details'] ?? null], array_filter((array) ($message['MessageEvents'] ?? []), 'is_array'))),
        ];
    }
}
