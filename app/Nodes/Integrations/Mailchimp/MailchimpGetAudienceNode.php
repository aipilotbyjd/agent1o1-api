<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpGetAudienceNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_get_audience';
    }

    public function name(): string
    {
        return 'Mailchimp: Get Audience';
    }

    public function description(): string
    {
        return 'Gets one audience: its counts, rates and sender defaults.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->shapeList((array) $this->mailchimp($run, $config, 'GET', '/lists/'.$this->listId($config)));
    }
}
