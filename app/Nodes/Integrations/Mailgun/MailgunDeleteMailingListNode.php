<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailgunDeleteMailingListNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_delete_mailing_list';
    }

    public function name(): string
    {
        return 'Mailgun: Delete Mailing List';
    }

    public function description(): string
    {
        return 'Deletes a mailing list and its members. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list'],
            'properties' => [
                ...$this->credentialFields(),
                'list' => $this->listField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->mailgun($run, $config, 'DELETE', '/v3/lists/'.$this->listAddress($config));

        return ['deleted' => true, 'list' => strtolower(trim((string) $config['list']))];
    }
}
