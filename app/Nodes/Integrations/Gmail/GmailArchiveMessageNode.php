<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailArchiveMessageNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_archive_message';
    }

    public function name(): string
    {
        return 'Gmail: Archive Message';
    }

    public function description(): string
    {
        return 'Takes a message out of the inbox. It stays in All Mail.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'gmail.messages', 'Pick a recent email, or map a message ID from an earlier step.', '18c2f0a1b2c3d4e5'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $message = $this->post($run, '/users/me/messages/'.$this->pathId($config, 'message_id', 'The message').'/modify', $config, ['removeLabelIds' => ['INBOX']]);

        return ['archived' => true, 'message_id' => $message['id'] ?? $config['message_id'], 'label_ids' => $message['labelIds'] ?? []];
    }
}
