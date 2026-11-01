<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraUpdateCommentNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_update_comment';
    }

    public function name(): string
    {
        return 'Jira: Update Comment';
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
            'required' => ['issue', 'comment_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'issue' => Field::text('Issue', 'The issue key, e.g. APP-123.', 'APP-123'),
                'comment_id' => Field::text('Comment id', 'The "id" of a comment from List Comments or Add Comment.'),
                'body' => Field::textarea('Comment', 'The new text. Markdown is supported.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $comment = $this->request($run, $config, 'PUT', '/issue/'.rawurlencode($this->issueKey($config)).'/comment/'.rawurlencode($this->requiredString($config, 'comment_id', 'The comment id')), [], ['body' => JiraAdf::fromMarkdown($this->requiredString($config, 'body', 'The comment'))]);

        return $this->shapeComment($comment);
    }
}
