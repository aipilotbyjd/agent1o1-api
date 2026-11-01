<?php

namespace App\Nodes\Integrations\GoogleTasks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleTasksListTaskListsNode extends AbstractGoogleTasksNode
{
    public function type(): string
    {
        return 'google_tasks_list_task_lists';
    }

    public function name(): string
    {
        return 'Google Tasks: List Task Lists';
    }

    public function description(): string
    {
        return 'Lists the account\'s task lists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'max_results' => Field::integer('Max results', null, 100, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, '/users/@me/lists', $config, ['maxResults' => $config['max_results'] ?? 100]);

        return ['task_lists' => array_map(fn (array $list): array => [
            'id' => $list['id'] ?? null,
            'title' => $list['title'] ?? null,
            'updated' => $list['updated'] ?? null,
        ], (array) ($response['items'] ?? []))];
    }
}
