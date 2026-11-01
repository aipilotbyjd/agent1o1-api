<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinearUpdateInitiativeNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_update_initiative';
    }

    public function name(): string
    {
        return 'Linear: Update Initiative';
    }

    public function description(): string
    {
        return 'Changes an initiative\'s details, and links projects to it or takes them off. Only the fields you fill in change.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['initiative'],
            'properties' => [
                ...$this->credentialFields(),
                'initiative' => Field::dynamic('Initiative', 'linear.initiatives', 'Pick an initiative, or give its name or id.', 'Grow EMEA'),
                'name' => Field::text('Name'),
                'description' => Field::textarea('Description', 'Replaces the description. Markdown is supported.'),
                'status' => Field::select('Status', ['Planned' => 'Planned', 'Active' => 'Active', 'Completed' => 'Completed']),
                'target_date' => Field::datetime('Target date'),
                'owner' => Field::dynamic('Owner', 'linear.users', 'Who owns it.', 'ada@acme.com'),
                'link_projects' => Field::dynamic('Link projects', 'linear.projects', 'Projects to add to the initiative.', 'Website relaunch', multiple: true),
                'unlink_projects' => Field::dynamic('Unlink projects', 'linear.projects', 'Projects to take off the initiative.', 'Old site', multiple: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->resolveId($run, $config, 'initiative', $this->requiredString($config, 'initiative', 'The initiative'));

        $input = array_filter([
            'name' => filled($config['name'] ?? null) ? (string) $config['name'] : null,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'status' => filled($config['status'] ?? null) ? (string) $config['status'] : null,
            'targetDate' => $this->date($config['target_date'] ?? null, 'The target date'),
            'ownerId' => $this->resolveId($run, $config, 'user', $config['owner'] ?? null),
        ], fn (mixed $value): bool => $value !== null);

        $link = $this->csv($config['link_projects'] ?? null);
        $unlink = $this->csv($config['unlink_projects'] ?? null);

        if ($input === [] && $link === [] && $unlink === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        if ($input !== []) {
            $this->assertSucceeded($this->graphql($run, $config, 'mutation($id: String!, $input: InitiativeUpdateInput!) { initiativeUpdate(id: $id, input: $input) { success } }', ['id' => $id, 'input' => $input]), 'initiativeUpdate', 'update the initiative');
        }

        foreach ($link as $project) {
            $this->assertSucceeded($this->graphql($run, $config, 'mutation($input: InitiativeToProjectCreateInput!) { initiativeToProjectCreate(input: $input) { success } }', ['input' => ['initiativeId' => $id, 'projectId' => $this->resolveId($run, $config, 'project', $project)]]), 'initiativeToProjectCreate', 'link the project');
        }

        foreach ($unlink as $project) {
            $projectId = $this->resolveId($run, $config, 'project', $project);
            $links = (array) data_get($this->graphql($run, $config, 'query($filter: InitiativeToProjectFilter) { initiativeToProjects(filter: $filter, first: 5) { nodes { id } } }', ['filter' => ['initiative' => ['id' => ['eq' => $id]], 'project' => ['id' => ['eq' => $projectId]]]]), 'initiativeToProjects.nodes', []);

            foreach ($links as $link) {
                $this->assertSucceeded($this->graphql($run, $config, 'mutation($id: String!) { initiativeToProjectDelete(id: $id) { success } }', ['id' => $link['id']]), 'initiativeToProjectDelete', 'unlink the project');
            }
        }

        $initiative = $this->graphql($run, $config, 'query($id: String!) { initiative(id: $id) { '.self::INITIATIVE_FIELDS.' } }', ['id' => $id])['initiative'] ?? [];

        return $this->shapeInitiative((array) $initiative);
    }
}
