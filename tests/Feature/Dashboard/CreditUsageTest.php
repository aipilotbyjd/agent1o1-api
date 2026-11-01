<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

/**
 * Bills a node run of `$workflow` the way the engine does: through the
 * ledger, so the endpoint's join back to a workflow is exercised rather than
 * bypassed.
 */
function chargeNodeRun(Workflow $workflow, int $credits): NodeRun
{
    $run = Run::factory()->forWorkflow($workflow)->create();
    $nodeRun = NodeRun::factory()->forRun($run)->create();

    app(DeductCreditsAction::class)->execute(
        $workflow->workspace, CreditTransactionType::NodeRun, $nodeRun->id, $credits, 'node',
    );

    return $nodeRun;
}

function chargeAgentTurn(Agent $agent, int $credits): AgentMessage
{
    $session = AgentSession::factory()->forAgent($agent)->create();
    $message = AgentMessage::factory()->forSession($session)->assistant()->create();

    app(DeductCreditsAction::class)->execute(
        $agent->workspace, CreditTransactionType::AgentStep, $message->id, $credits, 'turn',
    );

    return $message;
}

it('splits window spend by what caused each charge', function () {
    $workspace = $this->workspace;
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    chargeNodeRun($workflow, 30);
    chargeNodeRun($workflow, 20);
    chargeAgentTurn($agent, 12);

    Passport::actingAs($this->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/credit-usage");

    $response->assertOk();
    expect($response->json('data.total_credits'))->toBe(62);
    expect($response->json('data.by_source_type.node_run'))->toBe(50);
    expect($response->json('data.by_source_type.agent_step'))->toBe(12);
    // Every type is present even at zero, so a chart's legend is stable.
    expect($response->json('data.by_source_type.eval_case'))->toBe(0);
});

it('returns a zero-filled daily burn series', function () {
    $workspace = $this->workspace;
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $this->travelTo(now()->subDays(2), fn () => chargeNodeRun($workflow, 40));
    chargeNodeRun($workflow, 5);

    Passport::actingAs($this->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/credit-usage?days=5");

    $response->assertOk();

    $series = collect($response->json('data.series'));
    expect($series)->toHaveCount(5);
    expect($series->firstWhere('date', now()->subDays(2)->format('Y-m-d'))['credits'])->toBe(40);
    expect($series->firstWhere('date', now()->format('Y-m-d'))['credits'])->toBe(5);
    expect($series->firstWhere('date', now()->subDays(4)->format('Y-m-d'))['credits'])->toBe(0);
    expect($series->sum('credits'))->toBe(45);
});

it('leaves spend older than the window out', function () {
    $workspace = $this->workspace;
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $this->travelTo(now()->subDays(10), fn () => chargeNodeRun($workflow, 100));
    chargeNodeRun($workflow, 7);

    Passport::actingAs($this->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/credit-usage?days=7");

    $response->assertOk();
    expect($response->json('data.total_credits'))->toBe(7);
});

it('ranks the workflows and agents that cost the most', function () {
    $workspace = $this->workspace;
    $expensive = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Enrichment']);
    $cheap = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Ping']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['name' => 'Support bot']);

    chargeNodeRun($expensive, 60);
    chargeNodeRun($expensive, 15);
    chargeNodeRun($cheap, 4);
    chargeAgentTurn($agent, 9);

    Passport::actingAs($this->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/credit-usage");

    $response->assertOk();
    expect($response->json('data.top_workflows.0'))->toMatchArray([
        'workflow_id' => $expensive->id,
        'name' => 'Enrichment',
        'credits' => 75,
    ]);
    expect($response->json('data.top_workflows.1.credits'))->toBe(4);
    expect($response->json('data.top_agents.0'))->toMatchArray([
        'agent_id' => $agent->id,
        'name' => 'Support bot',
        'credits' => 9,
    ]);
});

it('still names a workflow that has since been deleted', function () {
    $workspace = $this->workspace;
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Retired flow']);
    chargeNodeRun($workflow, 25);
    $workflow->delete();

    Passport::actingAs($this->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/credit-usage");

    $response->assertOk();
    expect($response->json('data.top_workflows.0.name'))->toBe('Retired flow');
});

it('never counts another workspace spend', function () {
    $workspace = $this->workspace;
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    chargeNodeRun(Workflow::factory()->forWorkspace($other)->create(), 500);

    Passport::actingAs($this->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/credit-usage");

    $response->assertOk();
    expect($response->json('data.total_credits'))->toBe(0);
    expect($response->json('data.top_workflows'))->toBe([]);
});
