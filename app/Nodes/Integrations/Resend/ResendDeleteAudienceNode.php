<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ResendDeleteAudienceNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_delete_audience';
    }

    public function name(): string
    {
        return 'Resend: Delete Audience';
    }

    public function description(): string
    {
        return 'Deletes an audience and the contacts in it. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['audience_id'],
            'properties' => [
                ...$this->credentialFields(),
                'audience_id' => $this->audienceField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathPart($config, 'audience_id', 'The audience');

        $this->resend($run, $config, 'DELETE', "/audiences/{$id}");

        return ['deleted' => true, 'audience_id' => trim((string) $config['audience_id'])];
    }
}
