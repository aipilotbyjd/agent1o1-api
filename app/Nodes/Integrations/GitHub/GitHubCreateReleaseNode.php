<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubCreateReleaseNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_release';
    }

    public function name(): string
    {
        return 'GitHub: Create Release';
    }

    public function description(): string
    {
        return 'Publishes a release for a tag.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'tag_name'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'tag_name' => Field::text('Tag', 'Made from the target below if it does not exist.', 'v1.2.0'),
                'name' => Field::text('Release title', 'Defaults to the tag.'),
                'body' => Field::textarea('Release notes', 'Markdown supported.'),
                'target' => Field::dynamic('Target branch', 'github.branches', 'Where a new tag is made. Defaults to the default branch.', dependsOn: ['repo']),
                'generate_notes' => Field::boolean('Write the notes automatically', 'GitHub lists the changes since the last release.'),
                'draft' => Field::boolean('Save as draft'),
                'prerelease' => Field::boolean('Mark as pre-release'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        return $this->post($run, "/repos/{$repo}/releases", $config, [
            'tag_name' => trim((string) $config['tag_name']),
            'target_commitish' => trim((string) ($config['target'] ?? '')) ?: null,
            'name' => trim((string) ($config['name'] ?? '')) ?: null,
            'body' => trim((string) ($config['body'] ?? '')) ?: null,
            'generate_release_notes' => ($config['generate_notes'] ?? false) ? true : null,
            'draft' => ($config['draft'] ?? false) ? true : null,
            'prerelease' => ($config['prerelease'] ?? false) ? true : null,
        ]);
    }
}
