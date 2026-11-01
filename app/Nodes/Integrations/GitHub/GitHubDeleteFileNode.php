<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class GitHubDeleteFileNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_delete_file';
    }

    public function name(): string
    {
        return 'GitHub: Delete File';
    }

    public function description(): string
    {
        return 'Deletes a file from a repository with a commit.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'path', 'message'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'branch' => Field::dynamic('Branch', 'github.branches', 'Defaults to the default branch.', dependsOn: ['repo']),
                'path' => Field::text('File path', null, 'docs/old.md'),
                'message' => Field::text('Commit message', null, 'Remove old doc'),
                'sha' => Field::advanced(Field::text('File SHA', 'Found automatically. Set only to delete a specific version.')),
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

        if ($sha === null) {
            throw new RuntimeException('GitHub: there is no file at that path to delete.');
        }

        $result = $this->delete($run, "/repos/{$repo}/contents/{$path}", $config, ['message' => $config['message'], 'sha' => $sha, 'branch' => $branch]);

        return ['deleted' => true, 'path' => $config['path'], 'commit_sha' => $result['commit']['sha'] ?? null];
    }
}
