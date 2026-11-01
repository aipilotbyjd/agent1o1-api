<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailDeleteMessageNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_delete_message';
    }

    public function name(): string
    {
        return 'Gmail: Delete Message';
    }

    public function description(): string
    {
        return 'Moves a message to trash.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->post($run, "/users/me/messages/{$config['message_id']}/trash", $config);

        return ['trashed' => true, 'message_id' => $config['message_id']];
    }
}
