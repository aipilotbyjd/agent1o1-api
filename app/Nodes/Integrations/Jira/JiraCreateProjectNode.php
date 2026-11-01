<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class JiraCreateProjectNode extends AbstractJiraNode
{
    /**
     * The templates offered: label by choice.
     */
    private const array TEMPLATES = [
        'kanban' => 'Software: Kanban',
        'scrum' => 'Software: Scrum',
        'task_tracking' => 'Business: Task tracking',
        'project_management' => 'Business: Project management',
        'it_service' => 'Service management: IT service',
    ];

    /**
     * Each choice's project type and Jira template key.
     */
    private const array TEMPLATE_KEYS = [
        'kanban' => ['software', 'com.pyxis.greenhopper.jira:gh-simplified-agility-kanban'],
        'scrum' => ['software', 'com.pyxis.greenhopper.jira:gh-simplified-agility-scrum'],
        'task_tracking' => ['business', 'com.atlassian.jira-core-project-templates:jira-core-simplified-task-tracking'],
        'project_management' => ['business', 'com.atlassian.jira-core-project-templates:jira-core-simplified-project-management'],
        'it_service' => ['service_desk', 'com.atlassian.servicedesk:simplified-it-service-management'],
    ];

    public function type(): string
    {
        return 'jira_create_project';
    }

    public function name(): string
    {
        return 'Jira: Create Project';
    }

    public function description(): string
    {
        return 'Creates a project from a template, with a key, name and lead.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['key', 'name', 'template'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'key' => Field::text('Key', 'Short capital letters used in issue keys, e.g. APP (2 to 10 letters).', 'APP'),
                'name' => Field::text('Name'),
                'template' => Field::select('Template', self::TEMPLATES, 'What the project is set up like.', 'kanban'),
                'lead' => Field::dynamic('Lead', 'jira.users', 'Who leads it. Pick someone, or give an email or name. Leave empty to lead it yourself.', 'ada@acme.com'),
                'description' => Field::textarea('Description'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $key = strtoupper($this->requiredString($config, 'key', 'The key'));

        if (preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) !== 1) {
            throw new InvalidArgumentException("\"{$key}\" isn't a valid project key. Use 2 to 10 capital letters or digits, starting with a letter.");
        }

        [$type, $template] = self::TEMPLATE_KEYS[(string) ($config['template'] ?? 'kanban')] ?? throw new InvalidArgumentException('Pick one of the project templates.');

        $lead = $this->resolveUser($run, $config, $config['lead'] ?? null) ?? (string) ($this->request($run, $config, 'GET', '/myself')['accountId'] ?? '');

        $created = $this->request($run, $config, 'POST', '/project', [], array_filter([
            'key' => $key,
            'name' => $this->requiredString($config, 'name', 'The name'),
            'projectTypeKey' => $type,
            'projectTemplateKey' => $template,
            'leadAccountId' => $lead,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'assigneeType' => 'PROJECT_LEAD',
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeProject($this->request($run, $config, 'GET', '/project/'.rawurlencode((string) ($created['key'] ?? $key)), ['expand' => 'description,lead']));
    }
}
