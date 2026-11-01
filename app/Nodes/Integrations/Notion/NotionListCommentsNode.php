<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionListCommentsNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_list_comments';
    }

    public function name(): string
    {
        return 'Notion: List Comments';
    }

    public function description(): string
    {
        return 'Lists the open comments on a page or block, oldest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page'],
            'properties' => [
                ...$this->credentialFields(),
                'page' => Field::dynamic('Page or block', 'notion.pages', 'Pick a page, or paste the link or id of a page or block.'),
                'limit' => Field::integer('Limit', 'The most comments to return (up to 500).', 100, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $comments = array_map($this->shapeComment(...), $this->list($run, 'get', '/comments', $config, ['block_id' => $this->id($config, 'page', 'The page')], max(1, min(500, (int) ($config['limit'] ?? 100)))));

        return ['comments' => $comments, 'count' => count($comments)];
    }
}
