<?php

use App\Enums\Billing\CreditTransactionType;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Ai\AiCallEvent;
use App\Models\Billing\CreditTransaction;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    Passport::actingAs($this->owner);
});

it('breaks a workflow run\'s cost down by step, and lists the models that served it', function () {
    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    $run = Run::factory()->forWorkspace($this->workspace)->create(['runnable_id' => $workflow->id, 'workflow_id' => $workflow->id, 'status' => RunStatus::Completed]);
    $step = NodeRun::factory()->create(['run_id' => $run->id, 'key' => 'a', 'type' => 'transform', 'status' => NodeRunStatus::Completed, 'finished_at' => now()]);
    CreditTransaction::query()->create(['workspace_id' => $this->workspace->id, 'usage_period_id' => $this->workspace->currentUsagePeriod()->id, 'source_type' => CreditTransactionType::NodeRun, 'source_id' => $step->id, 'credits' => 1]);
    AiCallEvent::factory()->create(['run_id' => $run->id, 'workspace_id' => $this->workspace->id, 'model' => 'gpt-x']);

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}/cost")
        ->assertOk()
        ->assertJsonPath('data.credits', 1)
        ->assertJsonPath('data.charged_credits', 1)
        ->assertJsonPath('data.in_progress', false)
        ->assertJsonPath('data.steps.0.key', 'a')
        ->assertJsonPath('data.models.0.model', 'gpt-x');
});

it('never shows a customer another workspace\'s run', function () {
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $run = Run::factory()->forWorkspace($other)->create();

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}/cost")->assertNotFound();
});

it('shows where the workspace\'s credits went, without provider costs', function () {
    CreditTransaction::query()->create(['workspace_id' => $this->workspace->id, 'usage_period_id' => $this->workspace->currentUsagePeriod()->id, 'source_type' => CreditTransactionType::AgentStep, 'source_id' => 'm1', 'credits' => 7]);
    AiCallEvent::factory()->create(['workspace_id' => $this->workspace->id, 'model' => 'gpt-x', 'provider_cost_usd' => 3.21]);

    $response = $this->getJson("/api/v1/workspaces/{$this->workspace->id}/billing/usage-insights?days=7")
        ->assertOk()
        ->assertJsonPath('data.total_credits', 7)
        ->assertJsonPath('data.by_kind.0.kind', 'agent_step')
        ->assertJsonPath('data.models.0.model', 'gpt-x')
        ->assertJsonCount(7, 'data.daily');

    expect($response->getContent())->not->toContain('3.21');
});
