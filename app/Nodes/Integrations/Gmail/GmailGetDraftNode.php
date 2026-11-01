<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Gmail\Concerns\ShapesGmailMessages;
use App\Nodes\Support\Field;

class GmailGetDraftNode extends AbstractGmailNode
{
    use ShapesGmailMessages;

    public function type(): string
    {
        return 'gmail_get_draft';
    }

    public function name(): string
    {
        return 'Gmail: Get Draft';
    }

    public function description(): string
    {
        return 'Reads a draft: recipients, subject and text.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $draft = $this->get($run, '/users/me/drafts/'.$this->pathId($config, 'draft_id', 'The draft').'?format=full', $config);

        return ['draft_id' => $draft['id'] ?? $config['draft_id'], ...$this->flatMessage((array) ($draft['message'] ?? []))];
    }
}
