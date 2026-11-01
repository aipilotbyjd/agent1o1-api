<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class GitHubCreateOrUpdateFileNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_or_update_file';
    }

    public function name(): string
    {
        return 'GitHub: Create or Update File';
    }

    public function description(): string
    {
        return 'Commits a text file to a repository: makes it, or replaces it if it exists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'path', 'content', 'message'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'branch' => Field::dynamic('Branch', 'github.branches', 'Defaults to the default branch.', dependsOn: ['repo']),
                'path' => Field::text('File path', null, 'docs/README.md'),
                'content' => Field::textarea('File content', 'The text the file holds.'),
                'message' => Field::text('Commit message', null, 'Update README'),
                'sha' => Field::advanced(Field::text('File SHA', 'Found automatically. Set only to update a specific version.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $path = $this->segment((string) $config['path']);
        $branch = trim((string) ($config['branch'] ?? '')) ?: null;
        $sha = trim((string) ($config['sha'] ?? '')) ?: null;

        if ($sha === null) {
            try {
                $sha = $this->get($run, "/repos/{$repo}/contents/{$path}", $config, ['ref' => $branch])['sha'] ?? null;
            } catch (RuntimeException $exception) {
                // A file that is not there yet is created; any other error is real.
                if (! str_contains($exception->getMessage(), 'Not Found')) {
                    throw $exception;
                }
            }
        }

        $result = $this->put($run, "/repos/{$repo}/contents/{$path}", $config, [
            'message' => $config['message'],
            'content' => base64_encode((string) $config['content']),
            'branch' => $branch,
            'sha' => $sha,
        ]);

        return [
            'path' => $result['content']['path'] ?? $config['path'],
            'sha' => $result['content']['sha'] ?? null,
            'url' => $result['content']['html_url'] ?? null,
            'commit_sha' => $result['commit']['sha'] ?? null,
            'commit_url' => $result['commit']['html_url'] ?? null,
            'created' => $sha === null,
        ];
    }
}
