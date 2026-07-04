<?php

use App\Models\ArchivedExecutionLog;
use App\Models\Execution;
use App\Models\ExecutionLog;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\ExecutionArchiveService;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->workflow = Workflow::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->execution = Execution::factory()->completed()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);
});

test('old logs are moved to cold storage', function () {
    ExecutionLog::create([
        'execution_id' => $this->execution->id,
        'workspace_id' => $this->workspace->id,
        'level' => 'info',
        'message' => 'Old log',
        'logged_at' => now()->subDays(30),
    ]);
    ExecutionLog::create([
        'execution_id' => $this->execution->id,
        'workspace_id' => $this->workspace->id,
        'level' => 'info',
        'message' => 'Recent log',
        'logged_at' => now(),
    ]);

    $archived = app(ExecutionArchiveService::class)->archiveOlderThan(14);

    expect($archived)->toBe(1)
        ->and(ExecutionLog::count())->toBe(1)
        ->and(ArchivedExecutionLog::count())->toBe(1)
        ->and(ArchivedExecutionLog::first()->message)->toBe('Old log');
});

test('old terminal executions are pruned', function () {
    Execution::factory()->completed()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'created_at' => now()->subDays(120),
    ]);

    $pruned = app(ExecutionArchiveService::class)->pruneOlderThan(90);

    expect($pruned)->toBe(1);
});
