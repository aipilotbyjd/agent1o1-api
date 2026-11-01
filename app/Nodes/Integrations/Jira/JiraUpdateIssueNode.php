<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class JiraUpdateIssueNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_update_issue';
    }

    public function name(): string
    {
        return 'Jira: Update Issue';
    }

    public function description(): string
    {
        return 'Changes one issue, or every issue a JQL query or saved filter finds: summary, description, priority, labels, assignee, due date, a comment and custom fields. Only the fields you fill in change.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'filter_mode' => Field::select('Which issues', ['single' => 'One issue', 'jql' => 'Issues found by a JQL query', 'saved' => 'Issues in a saved filter'], 'Only one way of choosing issues is used at a time.', 'single'),
                'issue' => Field::text('Issue', 'One issue: its key, e.g. APP-123.', 'APP-123'),
                'jql' => Field::textarea('JQL', 'JQL query: the issues to change, e.g. project = APP AND priority = High.'),
                'saved_filter' => Field::dynamic('Saved filter', 'jira.filters', 'Saved filter: the filter whose issues change.', '10042'),
                'max_issues' => Field::advanced(Field::integer('Most issues to change', 'A guard for JQL and saved filters.', 25, 1, 100)),
                'summary' => Field::text('Summary'),
                'description' => Field::textarea('Description', 'Replaces the description. Markdown is supported.'),
                'priority' => Field::dynamic('Priority', 'jira.priorities', null, 'High'),
                'labels' => Field::dynamic('Set labels', 'jira.labels', 'Replaces the labels. Use Add or Remove to keep the others.', 'frontend', multiple: true),
                'add_labels' => Field::dynamic('Add labels', 'jira.labels', null, 'frontend', multiple: true),
                'remove_labels' => Field::dynamic('Remove labels', 'jira.labels', null, 'triage', multiple: true),
                'assignee' => Field::dynamic('Assignee', 'jira.users', 'Pick someone, give an email or name, or write "unassigned".', 'ada@acme.com'),
                'comment' => Field::textarea('Add a comment', 'Markdown is supported.'),
                'due_date' => Field::advanced(Field::datetime('Due date')),
                'custom_fields' => Field::advanced(Field::keyValue('Custom fields', 'A field (by name or id) and its value as text. Several choices: comma-separated. Parent and child choice: Parent > Child.')),
                'notify' => Field::advanced(Field::boolean('Notify watchers', null, true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $mode = (string) ($config['filter_mode'] ?? 'single');

        $keys = $mode === 'single'
            ? [$this->issueKey($config)]
            : array_column($this->searchIssues($run, $config, $this->filterJql($run, $config) ?: throw new InvalidArgumentException('The query finds no issues to change.'), max(1, min(100, (int) ($config['max_issues'] ?? 25))), ['summary']), 'key');

        $labels = $this->csv($config['labels'] ?? null);
        $added = $this->csv($config['add_labels'] ?? null);
        $removed = $this->csv($config['remove_labels'] ?? null);

        if ($labels !== [] && ($added !== [] || $removed !== [])) {
            throw new InvalidArgumentException('Set the labels, or add and remove some, not both.');
        }

        $updated = [];

        foreach ($keys as $key) {
            $fields = [];
            $update = [];

            if (filled($config['summary'] ?? null)) {
                $fields['summary'] = (string) $config['summary'];
            }

            if (filled($config['description'] ?? null)) {
                $fields['description'] = JiraAdf::fromMarkdown((string) $config['description']);
            }

            if (filled($config['priority'] ?? null)) {
                $fields['priority'] = ctype_digit((string) $config['priority']) ? ['id' => (string) $config['priority']] : ['name' => (string) $config['priority']];
            }

            if ($labels !== []) {
                $fields['labels'] = $labels;
            }

            if ($added !== [] || $removed !== []) {
                $update['labels'] = [...array_map(fn (string $label): array => ['add' => $label], $added), ...array_map(fn (string $label): array => ['remove' => $label], $removed)];
            }

            if (($assignee = $this->userField($run, $config, $config['assignee'] ?? null)) !== null) {
                $fields['assignee'] = $assignee;
            }

            if (filled($config['due_date'] ?? null)) {
                $fields['duedate'] = Carbon::parse((string) $config['due_date'])->toDateString();
            }

            if (is_array($config['custom_fields'] ?? null) && $config['custom_fields'] !== []) {
                $fields = [...$fields, ...$this->customFields($run, $config, $config['custom_fields'], $this->editMeta($run, $config, $key))];
            }

            if (filled($config['comment'] ?? null)) {
                $update['comment'] = [['add' => ['body' => JiraAdf::fromMarkdown((string) $config['comment'])]]];
            }

            if ($fields === [] && $update === []) {
                throw new InvalidArgumentException('Fill in at least one field to change.');
            }

            $this->request($run, $config, 'PUT', '/issue/'.rawurlencode($key), ['notifyUsers' => ($config['notify'] ?? true) === false ? 'false' : null], array_filter(['fields' => $fields ?: null, 'update' => $update ?: null]));
            $updated[] = $key;
        }

        $site = $this->site($run, $config)['url'];

        return [
            'updated' => $updated,
            'urls' => array_map(fn (string $key): string => "{$site}/browse/{$key}", $updated),
            'count' => count($updated),
        ];
    }
}
