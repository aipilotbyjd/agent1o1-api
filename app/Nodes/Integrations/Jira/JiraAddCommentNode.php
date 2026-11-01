<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraAddCommentNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_add_comment';
    }

    public function name(): string
    {
        return 'Jira: Add Comment';
    }

    public function description(): string
    {
        return 'Adds a comment to an issue. On a Service Management issue it can be internal (not shown to the customer).';
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
                ...$this->siteFields(),

                'issue' => Field::text('Issue', 'The issue key, e.g. APP-123.', 'APP-123'),
                'body' => Field::textarea('Comment', 'Markdown is supported.'),
                'internal' => Field::boolean('Internal note', 'Service Management only: hide it from the customer.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $comment = $this->request($run, $config, 'POST', '/issue/'.rawurlencode($this->issueKey($config)).'/comment', [], array_filter([
            'body' => JiraAdf::fromMarkdown($this->requiredString($config, 'body', 'The comment')),
            'properties' => ($config['internal'] ?? false) === true ? [['key' => 'sd.public.comment', 'value' => ['internal' => true]]] : null,
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeComment($comment);
    }
}
