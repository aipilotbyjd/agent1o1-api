<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\RunStatus;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowApproval;
use App\Services\Workflows\NodeTester;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

it('returns run totals, in-flight state and entity counts in one read', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Run::factory()->forWorkflow($workflow)->count(3)->create(['status' => RunStatus::Completed]);
    Run::factory()->forWorkflow($workflow)->create(['status' => RunStatus::Failed]);
    Run::factory()->forWorkflow($workflow)->create(['status' => RunStatus::Running]);
    Agent::factory()->forWorkspace($workspace)->create();
    // Pointed at the existing workflow: the factory otherwise conjures a
    // target workflow per trigger, which would move the workflow count.
    Trigger::factory()->forWorkflow($workflow)->create();
    Trigger::factory()->forWorkflow($workflow)->inactive()->create();

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard");

    $response->assertOk();
    expect($response->json('data.runs.total'))->toBe(5);
    expect($response->json('data.runs.completed'))->toBe(3);
    expect($response->json('data.runs.failed'))->toBe(1);
    expect($response->json('data.runs.in_flight'))->toBe(1);
    expect($response->json('data.runs.success_rate'))->toBe(0.75);
    expect($response->json('data.in_flight.running'))->toBe(1);
    expect($response->json('data.counts.workflows'))->toBe(1);
    expect($response->json('data.counts.agents'))->toBe(1);
    expect($response->json('data.counts.active_triggers'))->toBe(1);
    expect($response->json('data.counts.members'))->toBe(1);
    expect($response->json('data.recent_runs'))->toHaveCount(5);
    expect($response->json('data.window.days'))->toBe(30);
});

it('leaves success_rate null until something has finished', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    Run::factory()->forWorkflow($workflow)->create(['status' => RunStatus::Running]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard");

    $response->assertOk();
    expect($response->json('data.runs.total'))->toBe(1);
    expect($response->json('data.runs.success_rate'))->toBeNull();
});

it('counts neither single-node tests nor loop child runs as runs', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $parent = Run::factory()->forWorkflow($workflow)->create(['status' => RunStatus::Completed]);
    Run::factory()->forWorkflow($workflow)->create([
        'status' => RunStatus::Completed,
        'parent_run_id' => $parent->id,
        'loop_index' => 0,
    ]);
    Run::factory()->forWorkflow($workflow)->create([
        'status' => RunStatus::Completed,
        'trigger_type' => NodeTester::TRIGGER_TYPE,
    ]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard");

    $response->assertOk();
    expect($response->json('data.runs.total'))->toBe(1);
    expect($response->json('data.recent_runs'))->toHaveCount(1);
});

it('reports the current balance alongside what the window actually burned', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 30, 'a');
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::AgentStep, 2, 12, 'b');

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard");

    $response->assertOk();
    expect($response->json('data.credits.used_this_period'))->toBe(42);
    expect($response->json('data.credits.window_credits'))->toBe(42);
});

it('counts approvals still waiting on a human, however old', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    // Older than the default 30-day window on purpose: an approval nobody has
    // actioned in two months is the one the dashboard most needs to show.
    $stale = Run::factory()->forWorkflow($workflow)->create([
        'status' => RunStatus::AwaitingApproval,
        'created_at' => now()->subDays(60),
    ]);
    $nodeRun = NodeRun::factory()->forRun($stale)->create(['key' => 'gate', 'type' => 'human_approval']);
    WorkflowApproval::query()->create([
        'run_id' => $stale->id,
        'node_run_id' => $nodeRun->id,
        'requested_at' => now()->subDays(60),
    ]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard");

    $response->assertOk();
    expect($response->json('data.runs.total'))->toBe(0);
    expect($response->json('data.pending_approvals'))->toBe(1);
    expect($response->json('data.in_flight.awaiting_approval'))->toBe(1);
});

it('scopes every number to the workspace in the url', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);

    Run::factory()->forWorkflow(Workflow::factory()->forWorkspace($other)->create())
        ->create(['status' => RunStatus::Completed]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard");

    $response->assertOk();
    expect($response->json('data.runs.total'))->toBe(0);
    expect($response->json('data.counts.workflows'))->toBe(0);
});

it('lets a viewer read the dashboard but refuses a non-member', function () {
    $workspace = $this->workspace;
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    Passport::actingAs($viewer);
    $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard")->assertOk();

    Passport::actingAs(User::factory()->create());
    $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard")->assertForbidden();
});

it('rejects a window outside the supported range', function () {
    [$workspace, $owner] = [$this->workspace, $this->owner];
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard?days=0")
        ->assertStatus(422)
        ->assertJsonPath('errors.days.0', 'The days field must be at least 1.');

    $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard?days=400")->assertStatus(422);
});
