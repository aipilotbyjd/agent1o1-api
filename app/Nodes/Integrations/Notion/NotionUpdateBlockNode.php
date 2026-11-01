<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionUpdateBlockNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_update_block';
    }

    public function name(): string
    {
        return 'Notion: Update Block';
    }

    public function description(): string
    {
        return 'Changes the text of a block, or ticks a to-do.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['block'],
            'properties' => [
                ...$this->credentialFields(),
                'block' => Field::text('Block', 'The id (or link) of the block.'),
                'text' => Field::textarea('Text', 'The new text. Leave empty to keep it.'),
                'checked' => Field::select('To-do state', ['keep' => 'Keep as it is', 'checked' => 'Checked', 'unchecked' => 'Unchecked'], 'Only for a to-do block.', 'keep'),
                'content' => Field::json('Content (JSON)', 'The block\'s content object for anything richer, e.g. {"paragraph": {"rich_text": []}}. Used instead of the text above.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $blockId = $this->id($config, 'block', 'The block');
        $payload = $this->jsonField($config, 'content', 'Content');

        if ($payload === []) {
            $type = (string) ($this->get($run, "/blocks/{$blockId}", $config)['type'] ?? '');
            $content = [];

            if (trim((string) ($config['text'] ?? '')) !== '') {
                $content['rich_text'] = $this->richText((string) $config['text']);
            }

            if ($type === 'to_do' && in_array($config['checked'] ?? 'keep', ['checked', 'unchecked'], true)) {
                $content['checked'] = $config['checked'] === 'checked';
            }

            if ($content === []) {
                throw new \InvalidArgumentException('Notion: give new text, a to-do state or content to change.');
            }

            $payload = [$type => $content];
        }

        return $this->shapeBlock($this->patch($run, "/blocks/{$blockId}", $config, $payload));
    }
}
