<?php

use App\Enums\Role;
use App\Jobs\ProcessBuilderMessageJob;
use App\Models\User;
use App\Models\WorkflowBuilderMessage;
use App\Models\WorkflowBuilderSession;
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
});

test('sending a message returns 202 with message_id', function () {
    Queue::fake();

    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/messages", [
            'message' => 'Add a Slack node',
        ])
        ->assertStatus(202)
        ->assertJsonStructure(['data' => ['message_id']]);
});

test('sending a message dispatches the AI job on builder-ai queue', function () {
    Queue::fake();

    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/messages", [
            'message' => 'Add a webhook trigger',
        ]);

    Queue::assertPushedOn('builder-ai', ProcessBuilderMessageJob::class);
});

test('sending a message creates a pending assistant message', function () {
    Queue::fake();

    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/messages", [
            'message' => 'Add a Slack node',
        ]);

    $messageId = $response->json('data.message_id');
    $message = WorkflowBuilderMessage::find($messageId);

    expect($message->processing_status)->toBe('pending')
        ->and($message->role->value)->toBe('assistant');
});

test('cannot send a message to a completed session', function () {
    Queue::fake();

    $session = WorkflowBuilderSession::factory()->completed()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/messages", [
            'message' => 'Add a node',
        ])
        ->assertStatus(422);

    Queue::assertNothingPushed();
});

test('a user can list messages in their session oldest first', function () {
    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $session->messages()->create(['role' => 'user', 'content' => 'Hello', 'processing_status' => 'completed']);
    $session->messages()->create(['role' => 'assistant', 'content' => 'Hi!', 'processing_status' => 'completed']);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/messages")
        ->assertOk();

    expect($response->json('pagination.total'))->toBe(2)
        ->and($response->json('data.0.role'))->toBe('user');
});
