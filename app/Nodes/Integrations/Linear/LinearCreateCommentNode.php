<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearCreateCommentNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_create_comment';
    }

    public function name(): string
    {
        return 'Linear: Create Comment';
    }

    public function description(): string
    {
        return 'Adds a comment to an issue, or a reply to a comment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'issue' => Field::text('Issue', 'The identifier, e.g. ENG-123, or the issue id.', 'ENG-123'),
                'body' => Field::textarea('Comment', 'Markdown is supported.'),
                'parent_comment' => Field::advanced(Field::text('Reply to comment', 'The "id" of a comment to reply to.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $input = array_filter([
            'issueId' => $this->issueId($run, $config, $config['issue'] ?? null),
            'body' => $this->requiredString($config, 'body', 'The comment'),
            'parentId' => filled($config['parent_comment'] ?? null) ? (string) $config['parent_comment'] : null,
        ], fn (mixed $value): bool => $value !== null);

        $data = $this->graphql($run, $config, 'mutation($input: CommentCreateInput!) { commentCreate(input: $input) { success comment { '.self::COMMENT_FIELDS.' } } }', ['input' => $input]);
        $this->assertSucceeded($data, 'commentCreate', 'add the comment');

        return $this->shapeComment((array) data_get($data, 'commentCreate.comment'));
    }
}
