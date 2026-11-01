<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ActiveCampaignAddTagNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_add_tag';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Add Tag to Contact';
    }

    public function description(): string
    {
        return 'Puts a tag on a contact, which can start automations that wait for it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['tag_id', 'contact'],
            'properties' => [
                ...$this->credentialFields(),
                'tag_id' => Field::dynamic('Tag', 'activecampaign.tags', 'Pick a tag.', 'customer', type: 'integer'),
                'contact' => $this->contactField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tag = $this->numericId($config, 'tag_id', 'The tag');
        $contact = $this->contactId($run, $config);

        $this->ac($run, $config, 'POST', '/contactTags', ['contactTag' => ['contact' => $contact, 'tag' => $tag]]);

        return ['tagged' => true, 'tag_id' => $tag, 'contact_id' => $contact];
    }
}
