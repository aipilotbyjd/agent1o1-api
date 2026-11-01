<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class JiraUpdateProjectNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_update_project';
    }

    public function name(): string
    {
        return 'Jira: Update Project';
    }

    public function description(): string
    {
        return 'Changes a project\'s name, description, lead or link. Only the fields you fill in change.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'project' => Field::dynamic('Project', 'jira.projects', 'Pick a project, or give its key, e.g. APP.', 'APP'),
                'name' => Field::text('Name'),
                'description' => Field::textarea('Description'),
                'lead' => Field::dynamic('Lead', 'jira.users', 'Who leads it.', 'ada@acme.com'),
                'url' => Field::advanced(Field::text('Project link', 'A web address shown on the project.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $key = $this->projectKey($config);

        $body = array_filter([
            'name' => filled($config['name'] ?? null) ? (string) $config['name'] : null,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'leadAccountId' => $this->resolveUser($run, $config, $config['lead'] ?? null),
            'url' => filled($config['url'] ?? null) ? (string) $config['url'] : null,
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        return $this->shapeProject($this->request($run, $config, 'PUT', '/project/'.rawurlencode($key), ['expand' => 'description,lead'], $body));
    }
}
