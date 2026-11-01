<?php

use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

it('returns one series point per day in the window, zero-filled', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Run::factory()->forWorkflow($workflow)->count(2)->create([
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(2),
    ]);
    Run::factory()->forWorkflow($workflow)->create([
        'status' => RunStatus::Failed,
        'created_at' => now(),
    ]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/run-stats?days=7");

    $response->assertOk();

    $series = collect($response->json('data.series'));
    expect($series)->toHaveCount(7);
    expect($series->pluck('date')->all())->toBe(
        collect(range(6, 0))->map(fn (int $ago): string => now()->subDays($ago)->format('Y-m-d'))->all(),
    );
    expect($series->firstWhere('date', now()->subDays(2)->format('Y-m-d')))
        ->toMatchArray(['total' => 2, 'completed' => 2, 'failed' => 0]);
    expect($series->firstWhere('date', now()->format('Y-m-d')))
        ->toMatchArray(['total' => 1, 'completed' => 0, 'failed' => 1]);
    expect($series->firstWhere('date', now()->subDays(5)->format('Y-m-d')))
        ->toMatchArray(['total' => 0, 'completed' => 0, 'failed' => 0]);
});

it('leaves runs older than the window out of the totals', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Run::factory()->forWorkflow($workflow)->create([
        'status' => RunStatus::Completed,
        'created_at' => now()->subDays(10),
    ]);
    Run::factory()->forWorkflow($workflow)->create([
        'status' => RunStatus::Completed,
        'created_at' => now()->subDay(),
    ]);

    Passport::actingAs($owner);

    expect($this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/run-stats?days=7")->json('data.totals.total'))->toBe(1);
    expect($this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/run-stats?days=30")->json('data.totals.total'))->toBe(2);
});

it('narrows the totals and series to one workflow when asked', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    $mine = Workflow::factory()->forWorkspace($workspace)->create();
    $other = Workflow::factory()->forWorkspace($workspace)->create();

    Run::factory()->forWorkflow($mine)->create(['status' => RunStatus::Completed]);
    Run::factory()->forWorkflow($other)->count(2)->create(['status' => RunStatus::Completed]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/run-stats?workflow_id={$mine->id}");

    $response->assertOk();
    expect($response->json('data.workflow_id'))->toBe($mine->id);
    expect($response->json('data.totals.total'))->toBe(1);
    // The leaderboard stays workspace-wide even when the rest is narrowed.
    expect($response->json('data.top_workflows'))->toHaveCount(2);
});

it('ranks the busiest workflows and names them', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    $busy = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Nightly sync']);
    $quiet = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Ad hoc']);

    Run::factory()->forWorkflow($busy)->count(3)->create(['status' => RunStatus::Completed]);
    Run::factory()->forWorkflow($busy)->create(['status' => RunStatus::Failed]);
    Run::factory()->forWorkflow($quiet)->create(['status' => RunStatus::Completed]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/run-stats");

    $response->assertOk();
    expect($response->json('data.top_workflows.0'))->toMatchArray([
        'workflow_id' => $busy->id,
        'name' => 'Nightly sync',
        'runs' => 4,
        'failed' => 1,
    ]);
    expect($response->json('data.top_workflows.1.workflow_id'))->toBe($quiet->id);
});

it('averages the wall-clock duration of the runs that finished', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Run::factory()->forWorkflow($workflow)->create([
        'status' => RunStatus::Completed,
        'started_at' => now()->subSeconds(10),
        'finished_at' => now(),
    ]);
    Run::factory()->forWorkflow($workflow)->create([
        'status' => RunStatus::Completed,
        'started_at' => now()->subSeconds(20),
        'finished_at' => now(),
    ]);
    // Still running: no duration to average in.
    Run::factory()->forWorkflow($workflow)->create(['status' => RunStatus::Running]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/run-stats");

    $response->assertOk();
    expect($response->json('data.totals.avg_duration_ms'))->toBe(15_000);
});

it('reports zeros rather than 404ing for a workflow in someone else workspace', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $foreign = Workflow::factory()->forWorkspace($other)->create();

    Run::factory()->forWorkflow($foreign)->create(['status' => RunStatus::Completed]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/run-stats?workflow_id={$foreign->id}");

    $response->assertOk();
    expect($response->json('data.totals.total'))->toBe(0);
});
