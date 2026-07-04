<?php

use App\Enums\Role;
use App\Models\ConnectorMetric;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ConnectorMetricService;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);
});

test('connector metrics roll up into a single daily row', function () {
    $service = app(ConnectorMetricService::class);

    $service->record($this->workspace->id, 'slack', true, 120);
    $service->record($this->workspace->id, 'slack', false, 80);

    $metric = ConnectorMetric::where('workspace_id', $this->workspace->id)->where('connector', 'slack')->first();

    expect(ConnectorMetric::count())->toBe(1)
        ->and($metric->total_calls)->toBe(2)
        ->and($metric->success_calls)->toBe(1)
        ->and($metric->failed_calls)->toBe(1)
        ->and($metric->total_duration_ms)->toBe(200);
});

test('connector metrics are listed for a workspace', function () {
    ConnectorMetric::factory()->create(['workspace_id' => $this->workspace->id, 'connector' => 'github']);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/connector-metrics")
        ->assertOk()
        ->assertJsonPath('data.0.connector', 'github');
});

test('a log streaming config can be created', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/log-streaming", [
            'destination' => 'http',
            'endpoint' => 'https://logs.test/ingest',
            'headers' => ['Authorization' => 'Bearer abc'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.destination', 'http');
});
