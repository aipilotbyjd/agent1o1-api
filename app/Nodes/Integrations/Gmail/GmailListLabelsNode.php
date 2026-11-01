<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class GmailListLabelsNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_list_labels';
    }

    public function name(): string
    {
        return 'Gmail: List Labels';
    }

    public function description(): string
    {
        return "Lists all labels in the user's mailbox.";
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
        return $this->get($run, '/users/me/labels', $config);
    }
}
