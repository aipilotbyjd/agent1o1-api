<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionCreatePageNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_create_page';
    }

    public function name(): string
    {
        return 'Notion: Create Page';
    }

    public function description(): string
    {
        return 'Creates a page inside another page, with a title and optional text.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['parent_page', 'title'],
            'properties' => [
                ...$this->credentialFields(),
                'parent_page' => Field::dynamic('Parent page', 'notion.pages', 'The page to create it in. Pick one, or paste its link or id.'),
                'title' => Field::text('Title'),
                'content' => Field::textarea('Content', 'Plain text. Every line becomes a paragraph.'),
                'icon' => Field::advanced(Field::text('Icon', 'An emoji for the page icon.', '📝')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $blocks = $this->blocksFromText((string) ($config['content'] ?? ''), 'paragraph');
        $first = array_slice($blocks, 0, self::APPEND_LIMIT);

        $page = $this->post($run, '/pages', $config, array_filter([
            'parent' => ['page_id' => $this->id($config, 'parent_page', 'The parent page')],
            'properties' => ['title' => ['title' => $this->richText((string) $config['title'])]],
            'children' => $first === [] ? null : $first,
            'icon' => filled($config['icon'] ?? null) ? ['type' => 'emoji', 'emoji' => (string) $config['icon']] : null,
        ], fn (mixed $value): bool => $value !== null));

        // Notion takes 100 blocks when the page is made; the rest go on after it.
        $this->appendInChunks($run, (string) $page['id'], $config, array_slice($blocks, self::APPEND_LIMIT));

        return $this->shapePage($page);
    }
}
