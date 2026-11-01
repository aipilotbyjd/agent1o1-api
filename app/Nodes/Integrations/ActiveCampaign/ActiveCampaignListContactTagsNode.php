<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ActiveCampaignListContactTagsNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_list_contact_tags';
    }

    public function name(): string
    {
        return 'ActiveCampaign: List Contact Tags';
    }

    public function description(): string
    {
        return 'Lists the tags a contact has.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact'],
            'properties' => [
                ...$this->credentialFields(),
                'contact' => $this->contactField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $contact = $this->contactId($run, $config);
        $links = array_values(array_filter((array) data_get((array) $this->ac($run, $config, 'GET', "/contacts/{$contact}/contactTags"), 'contactTags', []), 'is_array'));
        $tags = [];

        foreach ($links as $link) {
            $tag = (array) data_get((array) $this->ac($run, $config, 'GET', '/tags/'.(int) $link['tag']), 'tag', []);
            $tags[] = ['id' => (int) $link['tag'], 'name' => $tag['tag'] ?? null];
        }

        return ['tags' => $tags, 'count' => count($tags)];
    }
}
