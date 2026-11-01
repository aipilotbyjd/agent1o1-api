<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpArchiveMemberNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_archive_member';
    }

    public function name(): string
    {
        return 'Mailchimp: Archive Member';
    }

    public function description(): string
    {
        return 'Archives a person: removes them from the audience\'s active list but keeps their history, and they can be added again.';
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
        $this->mailchimp($run, $config, 'DELETE', $this->memberPath($config));

        return ['archived' => true, 'list_id' => $this->listId($config), 'email' => $this->email($config)];
    }
}
