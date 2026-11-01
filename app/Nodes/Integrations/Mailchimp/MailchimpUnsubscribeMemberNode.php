<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpUnsubscribeMemberNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_unsubscribe_member';
    }

    public function name(): string
    {
        return 'Mailchimp: Unsubscribe Member';
    }

    public function description(): string
    {
        return 'Unsubscribes a person from an audience. They stay on it as unsubscribed, so they can\'t be emailed.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        return $this->shapeMember((array) $this->mailchimp($run, $config, 'PATCH', $this->memberPath($config), ['status' => 'unsubscribed']));
    }
}
