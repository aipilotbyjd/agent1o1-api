<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearCreateIssueNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_create_issue';
    }

    public function name(): string
    {
        return 'Linear: Create Issue';
    }

    public function description(): string
    {
        return 'Creates an issue in a team, with status, priority, labels, assignee and project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['team', 'title'],
            'properties' => [
                ...$this->credentialFields(),
                'team' => Field::dynamic('Team', 'linear.teams', 'The team to create it in. Pick one, or give its name or key.', 'Engineering'),
                'title' => Field::text('Title'),
                'description' => Field::textarea('Description', 'Markdown is supported.'),
                'state' => Field::dynamic('Status', 'linear.states', 'Defaults to the team\'s first status, e.g. Backlog.', 'Backlog', uses: ['team']),
                'priority' => Field::select('Priority', ['urgent' => 'Urgent', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low', 'none' => 'No priority']),
                'labels' => Field::dynamic('Labels', 'linear.labels', 'Labels to add.', 'Bug', multiple: true, uses: ['team']),
                'assignee' => Field::dynamic('Assignee', 'linear.users', 'Who is responsible. Pick someone, or give a name or email.', 'ada@acme.com'),
                'project' => Field::dynamic('Project', 'linear.projects', 'The project it belongs to.', 'Website relaunch'),
                'parent' => Field::advanced(Field::text('Parent issue', 'Makes this a sub-issue of that issue, e.g. ENG-100.', 'ENG-100')),
                'due_date' => Field::advanced(Field::datetime('Due date')),
                'estimate' => Field::advanced(Field::integer('Estimate', 'Points, as the team\'s estimates are set up.', null, 0, 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $teamId = $this->resolveId($run, $config, 'team', $this->requiredString($config, 'team', 'The team'));

        $input = array_filter([
            'teamId' => $teamId,
            'title' => $this->requiredString($config, 'title', 'The title'),
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'stateId' => $this->resolveId($run, $config, 'state', $config['state'] ?? null, $teamId),
            'priority' => $this->priority($config['priority'] ?? null),
            'labelIds' => ($labels = $this->resolveLabelIds($run, $config, $config['labels'] ?? null, $teamId)) === [] ? null : $labels,
            'assigneeId' => $this->resolveId($run, $config, 'user', $config['assignee'] ?? null),
            'projectId' => $this->resolveId($run, $config, 'project', $config['project'] ?? null),
            'parentId' => filled($config['parent'] ?? null) ? $this->issueId($run, $config, $config['parent'], 'The parent issue') : null,
            'dueDate' => $this->date($config['due_date'] ?? null, 'The due date'),
            'estimate' => isset($config['estimate']) && $config['estimate'] !== '' ? (int) $config['estimate'] : null,
        ], fn (mixed $value): bool => $value !== null);

        $data = $this->graphql($run, $config, 'mutation($input: IssueCreateInput!) { issueCreate(input: $input) { success issue { '.self::ISSUE_FIELDS.' } } }', ['input' => $input]);
        $this->assertSucceeded($data, 'issueCreate', 'create the issue');

        return $this->shapeIssue((array) data_get($data, 'issueCreate.issue'));
    }
}
