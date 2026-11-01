<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpDeleteAudienceNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_delete_audience';
    }

    public function name(): string
    {
        return 'Mailchimp: Delete Audience';
    }

    public function description(): string
    {
        return 'Deletes an audience and all its members. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->listId($config);

        $this->mailchimp($run, $config, 'DELETE', "/lists/{$id}");

        return ['deleted' => true, 'list_id' => $id];
    }
}
