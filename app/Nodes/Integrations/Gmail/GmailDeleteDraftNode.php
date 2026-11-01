<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailDeleteDraftNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_delete_draft';
    }

    public function name(): string
    {
        return 'Gmail: Delete Draft';
    }

    public function description(): string
    {
        return 'Deletes a draft. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['draft_id'],
            'properties' => [
                ...$this->credentialFields(),
                'draft_id' => Field::text('Draft id', 'From List Drafts or Create Draft.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->delete($run, '/users/me/drafts/'.$this->pathId($config, 'draft_id', 'The draft'), $config);

        return ['deleted' => true, 'draft_id' => trim((string) $config['draft_id'])];
    }
}
