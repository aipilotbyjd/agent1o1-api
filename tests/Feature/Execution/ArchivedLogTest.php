<?php

use App\Enums\Role;
use App\Models\ArchivedExecutionLog;
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

test('archived execution logs can be queried', function () {
    ArchivedExecutionLog::create([
        'execution_id' => Str::uuid()->toString(),
        'workspace_id' => $this->workspace->id,
        'level' => 'info',
        'message' => 'Archived entry',
        'logged_at' => now()->subDays(40),
        'archived_at' => now(),
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/archived-logs")
        ->assertOk()
        ->assertJsonPath('data.data.0.message', 'Archived entry')
        ->assertJsonPath('data.meta.total', 1);
});
