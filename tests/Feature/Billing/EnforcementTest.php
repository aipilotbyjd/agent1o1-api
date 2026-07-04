<?php

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\UsagePeriod;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Queue;
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

    $this->workflow = Workflow::factory()->active()->withSimpleGraph()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
});

// ── Credit enforcement ────────────────────────────────────────────────────────

test('execution is blocked with 402 when the workspace is out of credits', function () {
    Queue::fake();

    UsagePeriod::factory()->create([
        'workspace_id' => $this->workspace->id,
        'credits_limit' => 5,
        'credits_used' => 5, // 0 remaining
        'is_current' => true,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/execute")
        ->assertStatus(402)
        ->assertJsonPath('success', false);

    Queue::assertNothingPushed();
});

test('execution is allowed when the workspace has credits', function () {
    Queue::fake();

    UsagePeriod::factory()->create([
        'workspace_id' => $this->workspace->id,
        'credits_limit' => 1000,
        'credits_used' => 0,
        'is_current' => true,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/execute")
        ->assertStatus(202);
});

test('running an execution consumes one credit', function () {
    $period = UsagePeriod::factory()->create([
        'workspace_id' => $this->workspace->id,
        'credits_limit' => 1000,
        'credits_used' => 0,
        'is_current' => true,
    ]);

    $execution = $this->workflow->executions()->create([
        'workspace_id' => $this->workspace->id,
        'status' => \App\Enums\ExecutionStatus::Pending,
        'triggered_by' => $this->user->id,
        'trigger_data' => [],
    ]);

    app(\App\Engine\WorkflowRunner::class)->run($execution);

    expect($period->fresh()->credits_used)->toBe(1);
});

// ── Seat enforcement ──────────────────────────────────────────────────────────

test('accepting an invitation is blocked once the plan seat limit is reached', function () {
    // Free plan (seats: 1); the owner already occupies the single seat.
    $rawToken = Str::random(64);
    Invitation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'invitee@example.com',
        'role' => Role::Member->value,
        'token_hash' => Invitation::hashToken($rawToken),
        'invited_by' => $this->user->id,
        'expires_at' => now()->addDays(7),
    ]);

    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    $this->actingAs($invitee, 'api')
        ->postJson("/api/v1/invitations/{$rawToken}/accept")
        ->assertStatus(422);

    expect($this->workspace->members()->count())->toBe(1);
});

// ── Pagination clamp ──────────────────────────────────────────────────────────

test('per_page is clamped to a safe maximum', function () {
    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows?per_page=100000")
        ->assertOk()
        ->assertJsonPath('pagination.per_page', 100);
});
