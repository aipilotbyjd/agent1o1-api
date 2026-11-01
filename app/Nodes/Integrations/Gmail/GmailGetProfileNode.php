<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class GmailGetProfileNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_get_profile';
    }

    public function name(): string
    {
        return 'Gmail: Get Profile';
    }

    public function description(): string
    {
        return 'Reads the connected mailbox: its address and how many messages and conversations it holds.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $profile = $this->get($run, '/users/me/profile', $config);

        return ['email' => $profile['emailAddress'] ?? null, 'messages_total' => $profile['messagesTotal'] ?? null, 'threads_total' => $profile['threadsTotal'] ?? null, 'history_id' => $profile['historyId'] ?? null];
    }
}
