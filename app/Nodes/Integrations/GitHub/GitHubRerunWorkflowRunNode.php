<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubRerunWorkflowRunNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_rerun_workflow_run';
    }

    public function name(): string
    {
        return 'GitHub: Re-run Workflow Run';
    }

    public function description(): string
    {
        return 'Runs a finished workflow run again.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'run_id'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'run_id' => Field::integer('Run ID', 'The "id" of the run, from List Workflow Runs.', null, 1),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $this->post($run, "/repos/{$repo}/actions/runs/{$config['run_id']}/rerun", $config);

        return ['rerun' => true, 'run_id' => $config['run_id']];
    }
}
