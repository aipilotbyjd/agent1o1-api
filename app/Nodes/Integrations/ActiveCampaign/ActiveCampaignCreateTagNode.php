<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ActiveCampaignCreateTagNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_create_tag';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Create Tag';
    }

    public function description(): string
    {
        return 'Creates a contact tag.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'customer'),
                'description' => Field::advanced(Field::text('Description')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = $this->requiredString($config, 'name', 'The name');
        $tag = (array) data_get((array) $this->ac($run, $config, 'POST', '/tags', ['tag' => ['tag' => $name, 'tagType' => 'contact', 'description' => (string) ($config['description'] ?? '')]]), 'tag', []);

        return ['id' => isset($tag['id']) ? (int) $tag['id'] : null, 'name' => $tag['tag'] ?? $name];
    }
}
