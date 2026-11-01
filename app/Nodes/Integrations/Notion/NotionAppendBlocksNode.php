<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionAppendBlocksNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_append_blocks';
    }

    public function name(): string
    {
        return 'Notion: Append Content';
    }

    public function description(): string
    {
        return 'Adds text blocks to the end of a page (or after a given block).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page'],
            'properties' => [
                ...$this->credentialFields(),
                'page' => Field::dynamic('Page or block', 'notion.pages', 'Pick a page, or paste the link or id of a page or block.'),
                'content' => Field::textarea('Content', 'Plain text. Every line becomes a block of the type below.'),
                'block_type' => Field::select('Block type', $this->blockTypeChoices(), default: 'paragraph'),
                'blocks' => Field::json('Blocks (JSON)', 'Notion block objects, used instead of the content above for anything richer (tables, images, columns).', 'array'),
                'after' => Field::advanced(Field::text('After block', 'The id of the block to add them after. Empty adds them at the end.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $blocks = $this->jsonField($config, 'blocks', 'Blocks');
        $type = (string) ($config['block_type'] ?? 'paragraph');

        if ($blocks === []) {
            $blocks = $this->blocksFromText((string) ($config['content'] ?? ''), array_key_exists($type, $this->blockTypeChoices()) ? $type : 'paragraph');
        }

        if ($blocks === []) {
            throw new \InvalidArgumentException('Notion: give some content or blocks to add.');
        }

        $parentId = $this->id($config, 'page', 'The page');
        $after = filled($config['after'] ?? null) ? $this->id($config, 'after', 'The block to add after') : null;

        $this->appendInChunks($run, $parentId, $config, array_values($blocks), $after);

        return ['parent_id' => $parentId, 'added' => count($blocks)];
    }
}
