<?php

use App\Enums\Queue;
use App\Jobs\Agents\ApplyReflectionJob;
use App\Jobs\Agents\RunReflectionJob;
use App\Jobs\Agents\SyncKnowledgeSourceJob;
use App\Jobs\Agents\SyncSkillSourceJob;
use App\Jobs\Knowledge\IngestKnowledgeJob;
use App\Models\Agents\Agent;
use App\Models\Agents\KnowledgeSource;
use App\Models\Agents\Reflection;
use App\Models\Agents\SkillSource;
use App\Models\Workspaces\Workspace;

it('keeps long syncs and reflections off the default queue', function (object $job) {
    expect($job->queue)->toBe(Queue::LongRunning->value)
        ->and($job->connection)->toBe(config('queue.long_running_connection'));
})->with([
    'knowledge source sync' => fn () => new SyncKnowledgeSourceJob(new KnowledgeSource),
    'skill source sync' => fn () => new SyncSkillSourceJob(new SkillSource),
    'knowledge ingest' => fn () => new IngestKnowledgeJob(new Workspace, 'text', null, 'default'),
    'reflection run' => fn () => new RunReflectionJob(new Agent),
    'reflection apply' => fn () => new ApplyReflectionJob(new Reflection),
]);

it('gives the long-running connection a retry window longer than any of its jobs', function () {
    $longest = max(
        (new SyncKnowledgeSourceJob(new KnowledgeSource))->timeout,
        (new SyncSkillSourceJob(new SkillSource))->timeout,
        (new IngestKnowledgeJob(new Workspace, 'text', null, 'default'))->timeout,
        (new RunReflectionJob(new Agent))->timeout,
        (new ApplyReflectionJob(new Reflection))->timeout,
    );

    expect(config('queue.connections.redis-long.retry_after'))->toBeGreaterThan($longest)
        ->and(config('horizon.defaults.supervisor-long-running.timeout'))->toBeGreaterThanOrEqual($longest)
        ->and(config('horizon.defaults.supervisor-long-running.queue'))->toBe([Queue::LongRunning->value]);
});
