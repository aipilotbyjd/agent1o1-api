<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionCreateCommentNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_create_comment';
    }

    public function name(): string
    {
        return 'Notion: Create Comment';
    }

    public function description(): string
    {
        return 'Adds a comment to a page, or replies in a discussion.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'page' => Field::dynamic('Page', 'notion.pages', 'The page to comment on. Pick one, or paste its link or id.'),
                'discussion_id' => Field::advanced(Field::text('Discussion id', 'Reply in this discussion instead of starting a new comment on the page.')),
                'text' => Field::textarea('Comment'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        if (trim((string) ($config['text'] ?? '')) === '') {
            throw new \InvalidArgumentException('Notion: the comment cannot be empty.');
        }

        $target = filled($config['discussion_id'] ?? null)
            ? ['discussion_id' => $this->id($config, 'discussion_id', 'The discussion')]
            : ['parent' => ['page_id' => $this->id($config, 'page', 'The page')]];

        return $this->shapeComment($this->post($run, '/comments', $config, [...$target, 'rich_text' => $this->richText((string) $config['text'])]));
    }
}
