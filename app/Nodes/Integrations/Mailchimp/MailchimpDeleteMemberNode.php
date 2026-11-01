<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpDeleteMemberNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_delete_member';
    }

    public function name(): string
    {
        return 'Mailchimp: Delete Member Permanently';
    }

    public function description(): string
    {
        return 'Deletes a person and their history for good (for privacy requests). They can\'t be added back.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->mailchimp($run, $config, 'POST', $this->memberPath($config).'/actions/delete-permanent');

        return ['deleted' => true, 'list_id' => $this->listId($config), 'email' => $this->email($config)];
    }
}
