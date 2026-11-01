<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubDispatchWorkflowNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_dispatch_workflow';
    }

    public function name(): string
    {
        return 'GitHub: Run Workflow';
    }

    public function description(): string
    {
        return 'Starts a GitHub Actions workflow that has a workflow_dispatch trigger.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'workflow', 'ref'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'workflow' => Field::text('Workflow', 'The workflow file name or its ID.', 'deploy.yml'),
                'ref' => Field::dynamic('Branch or tag', 'github.branches', 'The ref the workflow runs on.', dependsOn: ['repo']),
                'inputs' => Field::keyValue('Inputs', 'Values for the workflow’s inputs.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $this->post($run, "/repos/{$repo}/actions/workflows/".rawurlencode((string) $config['workflow']).'/dispatches', $config, array_filter([
            'ref' => $config['ref'],
            'inputs' => ((array) ($config['inputs'] ?? [])) ?: null,
        ], fn ($value) => $value !== null));

        return ['dispatched' => true, 'workflow' => $config['workflow'], 'ref' => $config['ref']];
    }
}
