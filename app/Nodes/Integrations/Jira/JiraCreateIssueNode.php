<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class JiraCreateIssueNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_create_issue';
    }

    public function name(): string
    {
        return 'Jira: Create Issue';
    }

    public function description(): string
    {
        return 'Creates an issue (bug, task, story, epic, sub-task) with its details and custom fields.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project', 'issue_type', 'summary'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'project' => Field::dynamic('Project', 'jira.projects', 'Pick a project, or give its key, e.g. APP.', 'APP'),
                'issue_type' => Field::dynamic('Issue type', 'jira.issue_types', 'Bug, Task, Story, Epic, Sub-task... as the project offers them.', 'Task', uses: ['project']),
                'summary' => Field::text('Summary', 'The title.'),
                'description' => Field::textarea('Description', 'Markdown is supported.'),
                'priority' => Field::dynamic('Priority', 'jira.priorities', null, 'High'),
                'labels' => Field::dynamic('Labels', 'jira.labels', 'Labels to add.', 'frontend', multiple: true),
                'assignee' => Field::dynamic('Assignee', 'jira.users', 'Pick someone, or give an email or name.', 'ada@acme.com'),
                'reporter' => Field::dynamic('Reporter', 'jira.users', 'Defaults to the connected account.', 'ada@acme.com'),
                'parent' => Field::advanced(Field::text('Parent', 'For a sub-task or an issue in an epic: the parent\'s key.', 'APP-100')),
                'due_date' => Field::advanced(Field::datetime('Due date')),
                'components' => Field::advanced(Field::text('Components', 'Comma-separated component names.', 'API, Web')),
                'custom_fields' => Field::advanced(Field::keyValue('Custom fields', 'A field (by name or id, e.g. Story Points or customfield_10016) and its value as text. Several choices: comma-separated. Parent and child choice: Parent > Child.')),
                'extra_fields' => Field::advanced(Field::json('Other fields (JSON)', 'Jira fields as JSON, merged in as they are, e.g. {"environment": "prod"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->projectKey($config);
        $type = $this->requiredString($config, 'issue_type', 'The issue type');

        $fields = [
            'project' => ctype_digit($project) ? ['id' => $project] : ['key' => $project],
            'issuetype' => $this->issueTypeRef($type),
            'summary' => $this->requiredString($config, 'summary', 'The summary'),
        ];

        if (filled($config['description'] ?? null)) {
            $fields['description'] = JiraAdf::fromMarkdown((string) $config['description']);
        }

        if (filled($config['priority'] ?? null)) {
            $fields['priority'] = ctype_digit((string) $config['priority']) ? ['id' => (string) $config['priority']] : ['name' => (string) $config['priority']];
        }

        if (($labels = $this->csv($config['labels'] ?? null)) !== []) {
            $fields['labels'] = $labels;
        }

        if (($assignee = $this->userField($run, $config, $config['assignee'] ?? null)) !== null) {
            $fields['assignee'] = $assignee;
        }

        if (($reporter = $this->userField($run, $config, $config['reporter'] ?? null)) !== null) {
            $fields['reporter'] = $reporter;
        }

        if (filled($config['parent'] ?? null)) {
            $fields['parent'] = ['key' => $this->issueKey($config, 'parent', 'The parent')];
        }

        if (filled($config['due_date'] ?? null)) {
            $fields['duedate'] = Carbon::parse((string) $config['due_date'])->toDateString();
        }

        if (($components = $this->csv($config['components'] ?? null)) !== []) {
            $fields['components'] = array_map(fn (string $name): array => ['name' => $name], $components);
        }

        if (is_array($config['custom_fields'] ?? null) && $config['custom_fields'] !== []) {
            $fields = [...$fields, ...$this->customFields($run, $config, $config['custom_fields'], $this->createMeta($run, $config, $project, $type))];
        }

        if (is_array($config['extra_fields'] ?? null)) {
            $fields = array_replace_recursive($fields, $config['extra_fields']);
        }

        $created = $this->request($run, $config, 'POST', '/issue', [], ['fields' => $fields]);

        return $this->getIssue($run, $config, (string) $created['key']);
    }
}
