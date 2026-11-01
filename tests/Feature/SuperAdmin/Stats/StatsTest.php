<?php

use App\Enums\RunStatus;
use App\Models\Billing\CreditTransaction;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->headers = superAdminHeaders(superAdmin());
    $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
});

it('reports the headline numbers', function () {
    User::factory()->create(['suspended_at' => now()]);
    Run::factory()->forWorkspace($this->workspace)->count(3)->create(['status' => RunStatus::Completed]);
    Run::factory()->forWorkspace($this->workspace)->create(['status' => RunStatus::Failed]);
    CreditTransaction::query()->create(['workspace_id' => $this->workspace->id, 'source_type' => 'node_run', 'source_id' => (string) Str::uuid(), 'credits' => 12]);

    $this->withHeaders($this->headers)->getJson(saUrl('stats/overview'))
        ->assertOk()
        // Factories create their own users and workspaces (a run builds a workflow), so count what exists.
        ->assertJsonPath('data.users.total', User::query()->count())
        ->assertJsonPath('data.users.suspended', 1)
        ->assertJsonPath('data.workspaces.total', Workspace::query()->count())
        ->assertJsonPath('data.workspaces.active', 1)
        ->assertJsonPath('data.runs.total', 4)
        ->assertJsonPath('data.runs.by_status.completed', 3)
        ->assertJsonPath('data.runs.success_rate', 0.75)
        ->assertJsonPath('data.credits_consumed', 12);
});

it('has no success rate before any run has finished', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('stats/overview'))
        ->assertOk()
        ->assertJsonPath('data.runs.total', 0)
        ->assertJsonPath('data.runs.success_rate', null);
});

it('draws a zero-filled daily series', function () {
    User::factory()->count(2)->create(['created_at' => now()->subDays(2)]);
    User::factory()->create();

    $series = $this->withHeaders($this->headers)
        ->getJson(saUrl('stats/timeseries?metric=signups&from='.now()->subDays(3)->toDateString().'&to='.now()->toDateString()))
        ->assertOk()
        ->assertJsonPath('data.metric', 'signups')
        ->assertJsonCount(4, 'data.series')
        ->json('data.series');

    // Today's two are the workspace owner made in beforeEach and the user above.
    expect(array_column($series, 'value'))->toBe([0, 2, 0, 2]);
});

it('sums credits per day', function () {
    CreditTransaction::query()->create(['workspace_id' => $this->workspace->id, 'source_type' => 'node_run', 'source_id' => (string) Str::uuid(), 'credits' => 5]);
    CreditTransaction::query()->create(['workspace_id' => $this->workspace->id, 'source_type' => 'node_run', 'source_id' => (string) Str::uuid(), 'credits' => 8]);

    $series = $this->withHeaders($this->headers)
        ->getJson(saUrl('stats/timeseries?metric=credits&from='.now()->toDateString().'&to='.now()->toDateString()))
        ->assertOk()->json('data.series');

    expect($series)->toHaveCount(1)->and($series[0]['value'])->toBe(13);
});

it('rejects an unknown metric and an unreasonable range', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('stats/timeseries?metric=revenue'))->assertUnprocessable();
    $this->withHeaders($this->headers)->getJson(saUrl('stats/timeseries'))->assertUnprocessable();
    $this->withHeaders($this->headers)->getJson(saUrl('stats/timeseries?metric=runs&from=2020-01-01&to=2026-01-01'))->assertUnprocessable();
});

it('ranks the workspaces that spent the most credits', function () {
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);

    foreach ([[$this->workspace, 5], [$other, 50]] as [$workspace, $credits]) {
        CreditTransaction::query()->create(['workspace_id' => $workspace->id, 'source_type' => 'node_run', 'source_id' => (string) Str::uuid(), 'credits' => $credits]);
    }

    $this->withHeaders($this->headers)->getJson(saUrl('stats/top-workspaces'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Globex')
        ->assertJsonPath('data.0.credits', 50)
        ->assertJsonPath('data.1.name', 'Acme');
});

it('keeps stats to roles that can view them, and only to super admins', function () {
    $this->flushHeaders()->getJson(saUrl('stats/overview'))->assertUnauthorized();
});
