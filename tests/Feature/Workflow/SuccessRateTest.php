<?php

use App\Enums\ExecutionStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
});

test('success_rate reflects completed vs failed executions', function () {
    foreach ([ExecutionStatus::Completed, ExecutionStatus::Completed, ExecutionStatus::Completed, ExecutionStatus::Failed] as $status) {
        $this->workflow->executions()->create([
            'workspace_id' => $this->workspace->id,
            'status' => $status,
            'trigger_data' => [],
        ]);
    }
    // A cancelled + a pending run must NOT count toward the rate.
    $this->workflow->executions()->create(['workspace_id' => $this->workspace->id, 'status' => ExecutionStatus::Cancelled, 'trigger_data' => []]);
    $this->workflow->executions()->create(['workspace_id' => $this->workspace->id, 'status' => ExecutionStatus::Pending, 'trigger_data' => []]);

    $this->workflow->refreshSuccessRate();

    expect((float) $this->workflow->fresh()->success_rate)->toBe(75.0);
});

test('success_rate is zero when there are no terminal executions', function () {
    $this->workflow->refreshSuccessRate();
    expect((float) $this->workflow->fresh()->success_rate)->toBe(0.0);
});
