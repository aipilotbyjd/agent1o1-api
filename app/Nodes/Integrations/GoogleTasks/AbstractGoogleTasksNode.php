<?php

namespace App\Nodes\Integrations\GoogleTasks;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Nodes\Integrations\Concerns\CallsGoogleJsonApi;

/**
 * Shared request handling for the Google Tasks node family.
 */
abstract class AbstractGoogleTasksNode implements DeclaresEffect, NodeContract
{
    use CallsGoogleJsonApi;

    public function category(): string
    {
        return 'google_tasks';
    }

    protected function baseUrl(): string
    {
        return 'https://tasks.googleapis.com/tasks/v1';
    }

    protected function appName(): string
    {
        return 'Google Tasks';
    }

    /**
     * `/lists/{id}/tasks` (and `/{taskId}` when given). A list defaults to
     * the account's main one, which Tasks calls `@default`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function tasksPath(array $config, ?string $taskId = null): string
    {
        $path = '/lists/'.rawurlencode((string) (($config['task_list_id'] ?? '') ?: '@default')).'/tasks';

        return $taskId === null ? $path : $path.'/'.rawurlencode($taskId);
    }

    /**
     * A due date as Tasks wants it: midnight UTC of the day given (Tasks
     * keeps only the date). Empty when none was given.
     */
    protected function dueDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : substr($value, 0, 10).'T00:00:00.000Z';
    }
}
