<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearListCommentsNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_list_comments';
    }

    public function name(): string
    {
        return 'Linear: List Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on an issue, oldest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue'],
            'properties' => [
                ...$this->credentialFields(),
                'issue' => Field::text('Issue', 'The identifier, e.g. ENG-123, or the issue id.', 'ENG-123'),
                'limit' => Field::integer('Max results', null, 25, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $comments = $this->listAll(
            $run,
            $config,
            'query($id: String!, $first: Int, $after: String) { issue(id: $id) { comments(first: $first, after: $after) { nodes { '.self::COMMENT_FIELDS.' } pageInfo { hasNextPage endCursor } } } }',
            ['id' => $this->requiredString($config, 'issue', 'The issue')],
            'issue.comments',
            $this->limit($config, 25),
        );

        return ['comments' => array_map($this->shapeComment(...), $comments), 'count' => count($comments)];
    }
}
