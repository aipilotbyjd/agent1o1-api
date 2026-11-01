<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionDeleteBlockNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_delete_block';
    }

    public function name(): string
    {
        return 'Notion: Delete Block';
    }

    public function description(): string
    {
        return 'Deletes a block (moves it to the trash).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $blockId = $this->id($config, 'block', 'The block');
        $this->delete($run, "/blocks/{$blockId}", $config);

        return ['id' => $blockId, 'deleted' => true];
    }
}
