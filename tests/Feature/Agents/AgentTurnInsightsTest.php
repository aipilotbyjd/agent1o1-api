<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AutonomyMode;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Ai\ModelCatalog;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\AgentRunner;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Http\SsrfGuard;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create([
        'instructions' => 'Answer in one line.',
        'provider' => 'mistral',
        'model' => 'mistral-small',
        'autonomy_mode' => AutonomyMode::Autopilot,
    ]);
    $this->session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
});

it('hands a failed tool call back to the model instead of failing the turn', function () {
    app()->instance(SsrfGuard::class, new SsrfGuard(fn () => ['10.0.0.5']));
    $this->agent->toolBindings()->create([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://intranet.acme.test/notify', 'method' => 'POST'],
        'exposed_fields' => ['body'],
    ]);

    WorkspaceAgent::fake([new ToolCall('call-1', 'call_api', ['body' => ['text' => 'hi']]), 'That endpoint is not reachable.']);

    $reply = app(AgentRunner::class)->run($this->session, 'Ping the intranet.');

    $result = json_decode(collect($reply->tool_results)->firstWhere('id', 'call-1')['result'], true);
    expect($result['error'])->toContain('non-public address');
    expect($reply->content)->toBe('That endpoint is not reachable.');
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->status)->toBe(RunStatus::Completed);
    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Failed);
});

it('records the prompt, model and tools a chat turn was sent', function () {
    WorkspaceAgent::fake(['Hi.']);

    app(AgentRunner::class)->run($this->session, 'Hello');

    $context = Run::query()->where('runnable_id', $this->session->id)->sole()->agent_context;
    expect($context['provider'])->toBe('mistral');
    expect($context['model'])->toBe('mistral-small');
    expect($context['instructions'])->toContain('Answer in one line.');
    expect($context['tools'])->toContain('remember', 'forget');
});

it('returns the agent context only on the single-run view', function () {
    WorkspaceAgent::fake(['Hi.']);
    app(AgentRunner::class)->run($this->session, 'Hello');
    $run = Run::query()->where('runnable_id', $this->session->id)->sole();

    $this->actingAs($this->owner, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.run.agent_context.model', 'mistral-small');

    $this->actingAs($this->owner, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/runs")
        ->assertOk()
        ->assertJsonPath('data.0.id', $run->id)
        ->assertJsonMissingPath('data.0.agent_context');
});

it('grades with a model catalog entry picked as the grader', function () {
    $catalog = ModelCatalog::factory()->create(['slug' => 'strong-judge']);
    $catalog->routes()->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-opus-5-5', 'priority' => 1, 'is_enabled' => true]);

    expect(app(ModelCatalogResolver::class)->forJudging($this->agent, 'strong-judge'))
        ->toBe([['anthropic' => 'claude-opus-5-5'], null]);
});

it('treats a grader model that is not a catalog entry as a model on the agent\'s provider', function () {
    expect(app(ModelCatalogResolver::class)->forJudging($this->agent, 'mistral-large'))
        ->toBe(['mistral', 'mistral-large']);
});
