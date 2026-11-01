<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionListBlockChildrenNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_list_block_children';
    }

    public function name(): string
    {
        return 'Notion: Get Page Content';
    }

    public function description(): string
    {
        return 'Reads the blocks of a page (or of a block), with the text of them as one string.';
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
                'include_nested' => Field::boolean('Include nested blocks', 'Also read the blocks inside toggles, lists and columns (up to three levels).', false),
                'limit' => Field::integer('Limit', 'The most blocks to read (up to 500).', 100, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $limit = max(1, min(500, (int) ($config['limit'] ?? 100)));
        $blocks = $this->readChildren($run, $this->id($config, 'page', 'The page'), $config, ($config['include_nested'] ?? false) ? 3 : 1, $limit);

        $lines = array_map(fn (array $block): string => str_repeat('  ', $block['depth']).$this->lineOf($block), $blocks);

        return [
            'blocks' => $blocks,
            'count' => count($blocks),
            'text' => implode("\n", array_filter($lines, fn (string $line): bool => trim($line) !== '')),
        ];
    }

    /**
     * The blocks under one block, depth first, up to `$limit` in all.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    private function readChildren(Run $run, string $parentId, array $config, int $levels, int $limit, int $depth = 0): array
    {
        $blocks = [];

        foreach ($this->list($run, 'get', "/blocks/{$parentId}/children", $config, [], $limit) as $block) {
            if (count($blocks) >= $limit) {
                break;
            }

            $blocks[] = [...$this->shapeBlock($block), 'depth' => $depth];

            if (($block['has_children'] ?? false) === true && $depth + 1 < $levels && ($block['type'] ?? '') !== 'child_page') {
                $nested = $this->readChildren($run, (string) $block['id'], $config, $levels, $limit - count($blocks), $depth + 1);
                $blocks = [...$blocks, ...$nested];
            }
        }

        return array_slice($blocks, 0, $limit);
    }

    /**
     * One line of the page as text, marked up the way Markdown would.
     *
     * @param  array<string, mixed>  $block
     */
    private function lineOf(array $block): string
    {
        $text = (string) ($block['text'] ?? '');

        return match ($block['type'] ?? '') {
            'heading_1' => "# {$text}",
            'heading_2' => "## {$text}",
            'heading_3' => "### {$text}",
            'bulleted_list_item' => "- {$text}",
            'numbered_list_item' => "1. {$text}",
            'to_do' => ($block['checked'] ?? false ? '- [x] ' : '- [ ] ').$text,
            'quote' => "> {$text}",
            'divider' => '---',
            default => $text,
        };
    }
}
