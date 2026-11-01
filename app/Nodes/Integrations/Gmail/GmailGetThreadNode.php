<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailGetThreadNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_get_thread';
    }

    public function name(): string
    {
        return 'Gmail: Get Thread';
    }

    public function description(): string
    {
        return 'Reads a whole conversation: every message in it, oldest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['thread_id'],
            'properties' => [
                ...$this->credentialFields(),
                'thread_id' => Field::dynamic('Thread', 'gmail.threads', 'Pick a conversation, or map a thread ID from an earlier step.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, '/users/me/threads/'.rawurlencode((string) $config['thread_id']), $config);
    }
}
