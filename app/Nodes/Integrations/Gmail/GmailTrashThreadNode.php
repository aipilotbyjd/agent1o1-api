<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailTrashThreadNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_trash_thread';
    }

    public function name(): string
    {
        return 'Gmail: Move Conversation to Trash';
    }

    public function description(): string
    {
        return 'Moves a whole conversation to the trash.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['thread_id'],
            'properties' => [
                ...$this->credentialFields(),
                'thread_id' => Field::dynamic('Thread', 'gmail.threads', 'The conversation to trash.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $thread = $this->post($run, '/users/me/threads/'.$this->pathId($config, 'thread_id', 'The conversation').'/trash', $config);

        return ['trashed' => true, 'thread_id' => $thread['id'] ?? $config['thread_id']];
    }
}
