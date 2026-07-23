<?php

use App\Enums\ExecutionNodeStatus;
use App\Enums\ExecutionStatus;
use App\Enums\Role;
use App\Models\ExecutionNode;
use App\Models\Run;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
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
    $this->workflow = Workflow::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('returns empty schema when no completed executions exist', function () {
    $nodeId = Str::uuid()->toString();

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/nodes/{$nodeId}/output-schema")
        ->assertOk()
        ->assertJsonPath('data.sample_count', 0)
        ->assertJsonPath('data.schema', []);
});

test('infers schema from execution output history', function () {
    $nodeId = Str::uuid()->toString();
    $execution = Run::factory()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'status' => ExecutionStatus::Completed,
    ]);

    ExecutionNode::factory()->create([
        'execution_id' => $execution->id,
        'node_id' => $nodeId,
        'status' => ExecutionNodeStatus::Completed,
        'output_data' => ['name' => 'Alice', 'age' => 30],
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/nodes/{$nodeId}/output-schema")
        ->assertOk();

    expect($response->json('data.sample_count'))->toBe(1);
    expect($response->json('data.schema.name.type'))->toBe('string');
    expect($response->json('data.schema.age.type'))->toBe('integer');
});

test('infers nested object schema correctly', function () {
    $nodeId = Str::uuid()->toString();
    $execution = Run::factory()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'status' => ExecutionStatus::Completed,
    ]);

    ExecutionNode::factory()->create([
        'execution_id' => $execution->id,
        'node_id' => $nodeId,
        'status' => ExecutionNodeStatus::Completed,
        'output_data' => ['user' => ['id' => 1, 'email' => 'a@b.com']],
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/nodes/{$nodeId}/output-schema")
        ->assertOk();

    expect($response->json('data.schema.user.type'))->toBe('object');
    expect($response->json('data.schema.user.properties.id.type'))->toBe('integer');
    expect($response->json('data.schema.user.properties.email.type'))->toBe('string');
});

test('truncates long string samples', function () {
    $nodeId = Str::uuid()->toString();
    $execution = Run::factory()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'status' => ExecutionStatus::Completed,
    ]);

    ExecutionNode::factory()->create([
        'execution_id' => $execution->id,
        'node_id' => $nodeId,
        'status' => ExecutionNodeStatus::Completed,
        'output_data' => ['body' => str_repeat('x', 300)],
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/nodes/{$nodeId}/output-schema")
        ->assertOk();

    expect(mb_strlen($response->json('data.schema.body.sample')))->toBeLessThanOrEqual(201);
});

test('output schema requires workspace membership', function () {
    $outsider = User::factory()->create();
    $nodeId = Str::uuid()->toString();

    $this->actingAs($outsider, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/nodes/{$nodeId}/output-schema")
        ->assertForbidden();
});
