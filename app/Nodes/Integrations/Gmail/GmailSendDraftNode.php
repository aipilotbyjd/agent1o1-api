<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailSendDraftNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_send_draft';
    }

    public function name(): string
    {
        return 'Gmail: Send Draft';
    }

    public function description(): string
    {
        return 'Sends a draft made earlier, for example by Create Draft.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['draft_id'],
            'properties' => [
                ...$this->credentialFields(),
                'draft_id' => Field::text('Draft ID', 'The "id" that Create Draft returned.', 'r-1234567890'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, '/users/me/drafts/send', $config, ['id' => (string) $config['draft_id']]);
    }
}
