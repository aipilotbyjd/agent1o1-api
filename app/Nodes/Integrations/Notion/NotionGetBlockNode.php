<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionGetBlockNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_get_block';
    }

    public function name(): string
    {
        return 'Notion: Get Block';
    }

    public function description(): string
    {
        return 'Gets one block of a page.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['block'],
            'properties' => [
                ...$this->credentialFields(),
                'block' => Field::text('Block', 'The id (or link) of the block.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeBlock($this->get($run, '/blocks/'.$this->id($config, 'block', 'The block'), $config));
    }
}
