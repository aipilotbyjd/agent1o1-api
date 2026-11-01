<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KlaviyoRemoveProfileFromListNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_remove_profile_from_list';
    }

    public function name(): string
    {
        return 'Klaviyo: Remove Profile from List';
    }

    public function description(): string
    {
        return 'Takes a profile off a list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'profile'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->listField(),
                'profile' => $this->profileField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->resourceId($config, 'list_id', 'The list');
        $profile = $this->profileId($run, $config);

        $this->klaviyo($run, $config, 'DELETE', "/lists/{$list}/relationships/profiles/", ['data' => $this->profileRefs($profile)]);

        return ['removed' => true, 'list_id' => $list, 'profile_id' => $profile];
    }
}
