<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubRequestReviewersNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_request_reviewers';
    }

    public function name(): string
    {
        return 'GitHub: Request Reviewers';
    }

    public function description(): string
    {
        return 'Asks people to review a pull request.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'pull_number', 'reviewers'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'pull_number' => Field::integer('Pull request number', 'The number after # in the pull request\'s link.', null, 1),
                'reviewers' => Field::dynamic('Reviewers', 'github.assignees', 'People who can review in this repository.', dependsOn: ['repo'], multiple: true, type: 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return $this->post($run, "/repos/{$repo}/pulls/{$config['pull_number']}/requested_reviewers", $config, ['reviewers' => array_values(array_filter((array) $config['reviewers']))]);
    }
}
