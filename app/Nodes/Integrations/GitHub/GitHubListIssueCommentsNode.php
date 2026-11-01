<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubListIssueCommentsNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_list_issue_comments';
    }

    public function name(): string
    {
        return 'GitHub: List Issue Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on an issue or pull request.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'issue_number'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'issue_number' => Field::dynamic('Issue or pull request', 'github.issues', dependsOn: ['repo'], type: 'integer'),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return ['comments' => $this->get($run, "/repos/{$repo}/issues/{$config['issue_number']}/comments", $config, ['per_page' => $config['per_page'] ?? 30])];
    }
}
