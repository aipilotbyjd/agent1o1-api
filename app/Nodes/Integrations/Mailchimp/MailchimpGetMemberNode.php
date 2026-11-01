<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpGetMemberNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_get_member';
    }

    public function name(): string
    {
        return 'Mailchimp: Get Member';
    }

    public function description(): string
    {
        return 'Gets a person on an audience by email: status, tags, merge fields and engagement.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->shapeMember((array) $this->mailchimp($run, $config, 'GET', $this->memberPath($config)));
    }
}
