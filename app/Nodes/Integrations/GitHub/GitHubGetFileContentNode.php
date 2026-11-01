<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class GitHubGetFileContentNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_get_file_content';
    }

    public function name(): string
    {
        return 'GitHub: Get File Content';
    }

    public function description(): string
    {
        return 'Reads a text file from a repository.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'path'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'branch' => Field::dynamic('Branch', 'github.branches', 'Defaults to the default branch.', dependsOn: ['repo']),
                'path' => Field::text('File path', null, 'docs/README.md'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $file = $this->get($run, "/repos/{$repo}/contents/".$this->segment((string) $config['path']), $config, ['ref' => trim((string) ($config['branch'] ?? '')) ?: null]);

        if (array_is_list($file) || ($file['type'] ?? 'file') !== 'file') {
            throw new RuntimeException('GitHub: that path is a folder, not a file.');
        }

        if (($file['size'] ?? 0) > 0 && ($file['content'] ?? '') === '') {
            throw new RuntimeException('GitHub: the file is over 1 MB, which the contents API does not return.');
        }

        return [
            'path' => $file['path'] ?? $config['path'],
            'sha' => $file['sha'] ?? null,
            'size' => $file['size'] ?? 0,
            'content' => (string) base64_decode(str_replace("\n", '', (string) ($file['content'] ?? '')), false),
            'url' => $file['html_url'] ?? null,
            'download_url' => $file['download_url'] ?? null,
        ];
    }
}
