<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KlaviyoAddProfileToListNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_add_profile_to_list';
    }

    public function name(): string
    {
        return 'Klaviyo: Add Profile to List';
    }

    public function description(): string
    {
        return 'Puts a profile on a list without changing their consent. To collect consent, use Subscribe.';
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

        $this->klaviyo($run, $config, 'POST', "/lists/{$list}/relationships/profiles/", ['data' => $this->profileRefs($profile)]);

        return ['added' => true, 'list_id' => $list, 'profile_id' => $profile];
    }
}
