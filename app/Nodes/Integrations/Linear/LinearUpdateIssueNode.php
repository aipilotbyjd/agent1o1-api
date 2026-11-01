<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinearUpdateIssueNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_update_issue';
    }

    public function name(): string
    {
        return 'Linear: Update Issue';
    }

    public function description(): string
    {
        return 'Changes an issue\'s title, description, status, priority, assignee, labels, project or due date. Only the fields you fill in change.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue'],
            'properties' => [
                ...$this->credentialFields(),
                'issue' => Field::text('Issue', 'The identifier, e.g. ENG-123, or the issue id.', 'ENG-123'),
                'title' => Field::text('Title'),
                'description' => Field::textarea('Description', 'Replaces the description. Markdown is supported.'),
                'state' => Field::dynamic('Status', 'linear.states', 'Moves the issue to this status, e.g. Done.', 'In Progress'),
                'priority' => Field::select('Priority', ['urgent' => 'Urgent', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low', 'none' => 'No priority']),
                'assignee' => Field::dynamic('Assignee', 'linear.users', 'Hands the issue to this person.', 'ada@acme.com'),
                'unassign' => Field::boolean('Remove the assignee'),
                'add_labels' => Field::dynamic('Add labels', 'linear.labels', 'Labels to add; the issue\'s other labels stay.', 'Bug', multiple: true),
                'remove_labels' => Field::dynamic('Remove labels', 'linear.labels', 'Labels to take off.', 'Triage', multiple: true),
                'project' => Field::dynamic('Project', 'linear.projects', 'Moves the issue to this project.', 'Website relaunch'),
                'due_date' => Field::advanced(Field::datetime('Due date')),
                'estimate' => Field::advanced(Field::integer('Estimate', null, null, 0, 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->issueId($run, $config, $config['issue'] ?? null);
        $needsTeam = filled($config['state'] ?? null) || filled($config['add_labels'] ?? null) || filled($config['remove_labels'] ?? null);
        $teamId = $needsTeam ? $this->teamOfIssue($run, $config, $id) : null;

        $input = array_filter([
            'title' => filled($config['title'] ?? null) ? (string) $config['title'] : null,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'stateId' => $this->resolveId($run, $config, 'state', $config['state'] ?? null, $teamId),
            'priority' => $this->priority($config['priority'] ?? null),
            'assigneeId' => ($config['unassign'] ?? false) === true ? null : $this->resolveId($run, $config, 'user', $config['assignee'] ?? null),
            'addedLabelIds' => ($added = $this->resolveLabelIds($run, $config, $config['add_labels'] ?? null, $teamId)) === [] ? null : $added,
            'removedLabelIds' => ($removed = $this->resolveLabelIds($run, $config, $config['remove_labels'] ?? null, $teamId)) === [] ? null : $removed,
            'projectId' => $this->resolveId($run, $config, 'project', $config['project'] ?? null),
            'dueDate' => $this->date($config['due_date'] ?? null, 'The due date'),
            'estimate' => isset($config['estimate']) && $config['estimate'] !== '' ? (int) $config['estimate'] : null,
        ], fn (mixed $value): bool => $value !== null);

        // Taking the assignee off is a null in the input, which array_filter drops.
        if (($config['unassign'] ?? false) === true) {
            $input['assigneeId'] = null;
        }

        if ($input === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        $data = $this->graphql($run, $config, 'mutation($id: String!, $input: IssueUpdateInput!) { issueUpdate(id: $id, input: $input) { success issue { '.self::ISSUE_FIELDS.' } } }', ['id' => $id, 'input' => $input]);
        $this->assertSucceeded($data, 'issueUpdate', 'update the issue');

        return $this->shapeIssue((array) data_get($data, 'issueUpdate.issue'));
    }
}
