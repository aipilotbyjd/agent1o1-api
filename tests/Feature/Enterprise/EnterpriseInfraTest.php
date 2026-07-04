<?php

use App\Enums\Role;
use App\Models\GitSyncConfig;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;

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

test('git sync can be configured and the token is never exposed', function () {
    $this->actingAs($this->user, 'api')
        ->putJson("/api/v1/workspaces/{$this->workspace->id}/git-sync", [
            'repository' => 'acme/flows',
            'access_token' => 'ghp_secret',
        ])
        ->assertCreated()
        ->assertJsonPath('data.repository', 'acme/flows')
        ->assertJsonMissingPath('data.access_token');

    expect(GitSyncConfig::where('workspace_id', $this->workspace->id)->exists())->toBeTrue();
});

test('documents can be ingested and queried from the vector store', function () {
    Embeddings::fake();

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/vector-store/ingest", [
            'collection' => 'docs',
            'text' => 'Laravel is a PHP framework for building web applications.',
            'source' => 'handbook',
        ])
        ->assertCreated()
        ->assertJsonPath('data.chunks_stored', 1);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/vector-store/query", [
            'collection' => 'docs',
            'query' => 'What is Laravel?',
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
