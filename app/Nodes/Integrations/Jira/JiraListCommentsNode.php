<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraListCommentsNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_comments';
    }

    public function name(): string
    {
        return 'Jira: List Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on an issue.';
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
                ...$this->siteFields(),

                'issue' => Field::text('Issue', 'The issue key, e.g. APP-123.', 'APP-123'),
                'newest_first' => Field::boolean('Newest first'),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $comments = $this->listPages($run, $config, '/issue/'.rawurlencode($this->issueKey($config)).'/comment', ['orderBy' => ($config['newest_first'] ?? false) === true ? '-created' : 'created'], 'comments', $this->limit($config, 50, 500));

        return ['comments' => array_map($this->shapeComment(...), $comments), 'count' => count($comments)];
    }
}
