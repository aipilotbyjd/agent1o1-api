<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KlaviyoUnsubscribeProfileNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_unsubscribe_profile';
    }

    public function name(): string
    {
        return 'Klaviyo: Unsubscribe Profile';
    }

    public function description(): string
    {
        return 'Removes marketing email consent from a person on a list. Klaviyo processes it in the background.';
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
                'list_id' => $this->listField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->resourceId($config, 'list_id', 'The list');
        $email = $this->email($config);

        $this->klaviyo($run, $config, 'POST', '/profile-subscription-bulk-delete-jobs/', ['data' => [
            'type' => 'profile-subscription-bulk-delete-job',
            'attributes' => ['profiles' => ['data' => [['type' => 'profile', 'attributes' => ['email' => $email]]]]],
            'relationships' => ['list' => ['data' => ['type' => 'list', 'id' => $list]]],
        ]]);

        return ['requested' => true, 'list_id' => $list, 'email' => $email];
    }
}
