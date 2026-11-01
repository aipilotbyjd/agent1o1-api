<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailDeleteLabelNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_delete_label';
    }

    public function name(): string
    {
        return 'Gmail: Delete Label';
    }

    public function description(): string
    {
        return 'Deletes a label and takes it off every message. The messages stay. Built-in labels can\'t be deleted.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['label_id'],
            'properties' => [
                ...$this->credentialFields(),
                'label_id' => Field::dynamic('Label', 'gmail.labels', null, 'Clients'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->delete($run, '/users/me/labels/'.$this->pathId($config, 'label_id', 'The label'), $config);

        return ['deleted' => true, 'label_id' => trim((string) $config['label_id'])];
    }
}
