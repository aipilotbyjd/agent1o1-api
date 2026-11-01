<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GitHubCreatePullRequestReviewNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_pull_request_review';
    }

    public function name(): string
    {
        return 'GitHub: Review Pull Request';
    }

    public function description(): string
    {
        return 'Approves a pull request, asks for changes, or leaves a review comment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'pull_number', 'event'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'pull_number' => Field::integer('Pull request number', 'The number after # in the pull request\'s link.', null, 1),
                'event' => Field::select('Review', ['APPROVE' => 'Approve', 'REQUEST_CHANGES' => 'Request changes', 'COMMENT' => 'Comment only'], null, 'COMMENT'),
                'body' => Field::textarea('Comment', 'Markdown supported. Needed to request changes or comment only.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $body = trim((string) ($config['body'] ?? '')) ?: null;

        if (in_array($config['event'], ['REQUEST_CHANGES', 'COMMENT'], true) && $body === null) {
            throw new InvalidArgumentException('GitHub: write a comment to request changes or comment only.');
        }

        return $this->post($run, "/repos/{$repo}/pulls/{$config['pull_number']}/reviews", $config, ['event' => $config['event'], 'body' => $body]);
    }
}
