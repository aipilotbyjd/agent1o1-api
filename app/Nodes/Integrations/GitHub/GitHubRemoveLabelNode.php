<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubRemoveLabelNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_remove_label';
    }

    public function name(): string
    {
        return 'GitHub: Remove Label';
    }

    public function description(): string
    {
        return 'Removes one label from an issue or pull request.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'issue_number', 'label'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'issue_number' => Field::dynamic('Issue or pull request', 'github.issues', dependsOn: ['repo'], type: 'integer'),
                'label' => Field::dynamic('Label', 'github.labels', dependsOn: ['repo']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $this->delete($run, "/repos/{$repo}/issues/{$config['issue_number']}/labels/".rawurlencode((string) $config['label']), $config);

        return ['removed' => true, 'label' => $config['label']];
    }
}
