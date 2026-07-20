<?php

use App\Enums\Role;
use App\Models\AgentSkill;
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

test('a skill can be created', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agent-skills", [
            'name' => 'Refund Policy',
            'instructions' => 'Explain the refund policy.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'refund-policy')
        ->assertJsonPath('data.version', 1);
});

test('a skill can be created with category, icon, color, and tags', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agent-skills", [
            'name' => 'Competitor Research',
            'instructions' => 'Research competitors and summarize findings.',
            'category' => 'Research',
            'icon' => 'Puzzle',
            'color' => '#6366F1',
            'tags' => ['competitors', 'market'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.category', 'Research')
        ->assertJsonPath('data.icon', 'Puzzle')
        ->assertJsonPath('data.color', '#6366F1')
        ->assertJsonPath('data.tags', ['competitors', 'market']);
});

test('updating a skill bumps its version', function () {
    $skill = AgentSkill::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->putJson("/api/v1/workspaces/{$this->workspace->id}/agent-skills/{$skill->id}", [
            'instructions' => 'Updated instructions.',
        ])
        ->assertOk()
        ->assertJsonPath('data.version', 2);
});

test('references can be added, updated and removed', function () {
    $skill = AgentSkill::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $created = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agent-skills/{$skill->id}/references", [
            'title' => 'Docs',
            'content' => 'Reference content.',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($this->user, 'api')
        ->putJson("/api/v1/workspaces/{$this->workspace->id}/agent-skills/{$skill->id}/references/{$created}", [
            'title' => 'Updated Docs',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Docs');

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/agent-skills/{$skill->id}/references/{$created}")
        ->assertOk();

    expect($skill->references()->count())->toBe(0);
});

test('scripts can be added with a default language', function () {
    $skill = AgentSkill::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agent-skills/{$skill->id}/scripts", [
            'name' => 'Greeter',
            'description' => 'Greets the user.',
            'code' => 'return "hi";',
        ])
        ->assertCreated()
        ->assertJsonPath('data.language', 'php')
        ->assertJsonPath('data.is_enabled', true);
});

test('a skill can be deleted', function () {
    $skill = AgentSkill::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/agent-skills/{$skill->id}")
        ->assertOk();

    expect(AgentSkill::find($skill->id))->toBeNull();
});
