<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearUpdateCommentNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_update_comment';
    }

    public function name(): string
    {
        return 'Linear: Update Comment';
    }

    public function description(): string
    {
        return 'Changes the text of a comment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['comment_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'comment_id' => Field::text('Comment id', 'The "id" of a comment from List Comments or Create Comment.'),
                'body' => Field::textarea('Comment', 'The new text. Markdown is supported.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $data = $this->graphql($run, $config, 'mutation($id: String!, $input: CommentUpdateInput!) { commentUpdate(id: $id, input: $input) { success comment { '.self::COMMENT_FIELDS.' } } }', [
            'id' => $this->requiredString($config, 'comment_id', 'The comment id'),
            'input' => ['body' => $this->requiredString($config, 'body', 'The comment')],
        ]);
        $this->assertSucceeded($data, 'commentUpdate', 'update the comment');

        return $this->shapeComment((array) data_get($data, 'commentUpdate.comment'));
    }
}
