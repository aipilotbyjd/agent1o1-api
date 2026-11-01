<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraDeleteCommentNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_delete_comment';
    }

    public function name(): string
    {
        return 'Jira: Delete Comment';
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
            'required' => ['issue', 'comment_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'issue' => Field::text('Issue', 'The issue key, e.g. APP-123.', 'APP-123'),
                'comment_id' => Field::text('Comment id', 'The "id" of a comment from List Comments or Add Comment.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->requiredString($config, 'comment_id', 'The comment id');

        $this->request($run, $config, 'DELETE', '/issue/'.rawurlencode($this->issueKey($config)).'/comment/'.rawurlencode($id));

        return ['deleted' => true, 'id' => $id];
    }
}
