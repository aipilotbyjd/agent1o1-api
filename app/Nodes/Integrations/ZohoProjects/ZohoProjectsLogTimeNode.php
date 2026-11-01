<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ZohoProjectsLogTimeNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_log_time';
    }

    public function name(): string
    {
        return 'Zoho Projects: Log Time';
    }

    public function description(): string
    {
        return 'Logs hours against a task on a day.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'task_id', 'hours'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'task_id' => Field::text('Task id', 'An id from a previous step (a long number).'),
                'hours' => Field::text('Hours', 'e.g. 2.5 for two and a half hours.', '2.5'),
                'date' => Field::advanced(Field::datetime('Day', 'Empty is today.')),
                'notes' => Field::advanced(Field::textarea('Notes')),
                'billable' => Field::advanced(Field::boolean('Billable', null, true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->numericId($config, 'project_id', 'The project');
        $id = $this->numericId($config, 'task_id', 'The task');
        $hours = (string) ($config['hours'] ?? '');

        if (! is_numeric($hours) || (float) $hours <= 0 || (float) $hours > 24) {
            throw new InvalidArgumentException('The hours must be a number above zero and at most 24.');
        }

        $minutes = (int) round((float) $hours * 60);

        $this->projects($run, $config, 'POST', "/projects/{$project}/tasks/{$id}/timelogs", [], array_filter([
            'date' => $this->date($config, 'date') ?? Carbon::now()->format('Y-m-d'),
            'daily_log_hours' => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60),
            'bill_status' => ($config['billable'] ?? true) ? 'Billable' : 'Non Billable',
            'notes' => filled($config['notes'] ?? null) ? (string) $config['notes'] : null,
        ], fn (mixed $value): bool => $value !== null));

        return ['logged' => true, 'task_id' => $id, 'hours' => round($minutes / 60, 2)];
    }
}
