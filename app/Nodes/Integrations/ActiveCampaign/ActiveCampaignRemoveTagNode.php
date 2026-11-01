<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ActiveCampaignRemoveTagNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_remove_tag';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Remove Tag from Contact';
    }

    public function description(): string
    {
        return 'Takes a tag off a contact.';
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
        $links = array_values(array_filter((array) data_get((array) $this->ac($run, $config, 'GET', "/contacts/{$contact}/contactTags"), 'contactTags', []), 'is_array'));
        $link = collect($links)->first(fn (array $item): bool => (int) ($item['tag'] ?? 0) === $tag);

        if ($link === null) {
            return ['untagged' => false, 'tag_id' => $tag, 'contact_id' => $contact, 'reason' => 'The contact does not have this tag.'];
        }

        $this->ac($run, $config, 'DELETE', '/contactTags/'.(int) $link['id']);

        return ['untagged' => true, 'tag_id' => $tag, 'contact_id' => $contact];
    }
}
