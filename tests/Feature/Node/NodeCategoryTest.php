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

test('a single category can be shown', function () {
    $category = NodeCategory::factory()->create();

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/node-categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $category->id);
});

test('category show includes its active nodes', function () {
    $category = NodeCategory::factory()->create();
    Node::factory()->count(2)->create(['category_id' => $category->id, 'is_active' => true]);
    Node::factory()->create(['category_id' => $category->id, 'is_active' => false]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/node-categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.nodes_count', 2)
        ->assertJsonCount(2, 'data.nodes');
});

test('category show excludes inactive nodes', function () {
    $category = NodeCategory::factory()->create();
    Node::factory()->create(['category_id' => $category->id, 'is_active' => false]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/node-categories/{$category->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.nodes');
});

test('category show returns 404 for unknown category', function () {
    $this->actingAs($this->user, 'api')
        ->getJson('/api/v1/node-categories/'.Str::uuid())
        ->assertNotFound();
});
