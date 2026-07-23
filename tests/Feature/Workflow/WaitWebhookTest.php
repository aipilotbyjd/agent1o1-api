<?php

use App\Enums\ExecutionStatus;
use App\Jobs\ResumeWorkflowJob;
use App\Models\ExecutionCheckpoint;
use App\Models\Run;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->workflow = Workflow::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->token = Str::uuid()->toString();

    $this->execution = Run::factory()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'status' => ExecutionStatus::Waiting,
        'wait_token' => $this->token,
    ]);

    ExecutionCheckpoint::create([
        'execution_id' => $this->execution->id,
        'context_snapshot' => ['variables' => ['trigger_data' => []]],
        'output_buffer_snapshot' => [],
        'frontier_snapshot' => [],
    ]);
});

test('posting to the wait webhook resumes the execution', function () {
    Queue::fake();

    $this->postJson("/api/v1/webhook-wait/{$this->token}", ['approved' => true])
        ->assertStatus(202)
        ->assertJsonPath('data.execution_id', $this->execution->id);

    Queue::assertPushed(ResumeWorkflowJob::class, fn ($job) => $job->executionId === $this->execution->id);

    $execution = $this->execution->fresh();
    expect($execution->wait_token)->toBeNull()
        ->and($execution->checkpoint->context_snapshot['variables']['resume_data'])->toBe(['approved' => true]);
});

test('an unknown wait token returns 404', function () {
    Queue::fake();

    $this->postJson('/api/v1/webhook-wait/'.Str::uuid()->toString())
        ->assertNotFound();

    Queue::assertNotPushed(ResumeWorkflowJob::class);
});

test('a non-waiting execution cannot be resumed', function () {
    Queue::fake();

    $this->execution->update(['status' => ExecutionStatus::Completed]);

    $this->postJson("/api/v1/webhook-wait/{$this->token}")
        ->assertNotFound();
});
