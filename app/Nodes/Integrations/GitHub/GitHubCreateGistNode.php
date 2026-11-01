<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubCreateGistNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_gist';
    }

    public function name(): string
    {
        return 'GitHub: Create Gist';
    }

    public function description(): string
    {
        return 'Creates a gist with one file.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['filename', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'filename' => Field::text('File name', null, 'notes.md'),
                'content' => Field::textarea('File content'),
                'description' => Field::text('Description'),
                'public' => Field::boolean('Public', 'Anyone can find a public gist. A secret one is reachable only by its link.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $gist = $this->post($run, '/gists', $config, [
            'description' => trim((string) ($config['description'] ?? '')) ?: null,
            'public' => (bool) ($config['public'] ?? false),
            'files' => [trim((string) $config['filename']) => ['content' => (string) $config['content']]],
        ]);

        return ['id' => $gist['id'] ?? null, 'url' => $gist['html_url'] ?? null];
    }
}
