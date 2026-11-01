<?php

namespace App\Nodes\Integrations\GitHub;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GitHubListWorkflowRunsNode extends AbstractGitHubNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'github_list_workflow_runs';
    }

    public function name(): string
    {
        return 'GitHub: List Workflow Runs';
    }

    public function description(): string
    {
        return 'Lists the GitHub Actions workflow runs of a repository, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'branch' => Field::dynamic('Branch', 'github.branches', 'Only runs on this branch.', dependsOn: ['repo']),
                'status' => Field::select('Status', ['completed' => 'Finished', 'in_progress' => 'Running', 'queued' => 'Queued', 'failure' => 'Failed', 'success' => 'Succeeded'], 'A run that has finished, or only failed or succeeded ones.', 'completed'),
                'workflow' => Field::text('Workflow', 'Only this workflow, by file name or ID.', 'ci.yml'),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);

        $workflow = trim((string) ($config['workflow'] ?? ''));
        $path = $workflow === '' ? "/repos/{$repo}/actions/runs" : "/repos/{$repo}/actions/workflows/".rawurlencode($workflow).'/runs';

        $response = $this->get($run, $path, $config, [
            'branch' => trim((string) ($config['branch'] ?? '')) ?: null,
            'status' => ($config['status'] ?? 'completed') ?: null,
            'per_page' => $config['per_page'] ?? 30,
        ]);

        return ['runs' => $response['workflow_runs'] ?? [], 'total' => $response['total_count'] ?? 0];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'runs';
    }

    /**
     * The item's stable id.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['id']) ? $item['id'].':'.($item['run_attempt'] ?? 1) : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'GitHub: Workflow run finished';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a GitHub Actions run finishes. Choose "Failed" to watch for failures.';
    }

    /**
     * Read a wider page than the node's default, so a burst between two polls
     * isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'per_page' => 100];
    }

    /**
     * Newest first, as GitHub lists them.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
