<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearDeleteCommentNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_delete_comment';
    }

    public function name(): string
    {
        return 'Linear: Delete Comment';
    }

    public function description(): string
    {
        return 'Deletes a comment from an issue.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['comment_id'],
            'properties' => [
                ...$this->credentialFields(),
                'comment_id' => Field::text('Comment id', 'The "id" of a comment from List Comments or Create Comment.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->requiredString($config, 'comment_id', 'The comment id');

        $this->assertSucceeded($this->graphql($run, $config, 'mutation($id: String!) { commentDelete(id: $id) { success } }', ['id' => $id]), 'commentDelete', 'delete the comment');

        return ['deleted' => true, 'id' => $id];
    }
}
