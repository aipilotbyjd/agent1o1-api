<?php

use App\Enums\Role;
use App\Models\Node;
use App\Models\NodeCategory;
use App\Models\User;
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
});

test('the node catalog lists active nodes', function () {
    Node::factory()->count(2)->create();
    Node::factory()->create(['is_active' => false]);

    $this->actingAs($this->user, 'api')
        ->getJson('/api/v1/nodes')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('node categories are listed with counts', function () {
    $category = NodeCategory::factory()->create();
    Node::factory()->count(3)->create(['category_id' => $category->id]);

    $this->actingAs($this->user, 'api')
        ->getJson('/api/v1/node-categories')
        ->assertOk()
        ->assertJsonPath('data.0.nodes_count', 3);
});

test('a single node can be shown', function () {
    $node = Node::factory()->create();

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/nodes/{$node->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $node->id);
});

test('the sandbox executes a transform node and returns its output', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/nodes/sandbox", [
            'type' => 'transform',
            'config' => ['output' => ['greeting' => 'hello']],
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.output.greeting', 'hello');
});

test('the sandbox reports unknown node types', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/nodes/sandbox", [
            'type' => 'does_not_exist',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'failed');
});
