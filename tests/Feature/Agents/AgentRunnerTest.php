<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Ai\Agents\WorkspaceAgent;
use App\Ai\Tools\RememberTool;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\RunStatus;
use App\Exceptions\RunStateException;
use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\AgentRunner;
use App\Services\Agents\SkillInjector;
use App\Services\Agents\ToolRegistry;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Tools\Request;

it('sends a message with no tools and completes a run', function () {
    WorkspaceAgent::fake(['Hello there!']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);
    $reply = app(SendAgentMessageAction::class)->execute($session, 'Hi!');

    expect($reply->role)->toBe(AgentMessageRole::Assistant);
    expect($reply->content)->toBe('Hello there!');
    expect($reply->usage)->not->toBeNull();

    expect($session->messages)->toHaveCount(2);
    expect($session->messages->first()->role)->toBe(AgentMessageRole::User);
    expect($session->messages->first()->content)->toBe('Hi!');

    $run = Run::where('runnable_type', 'agent_session')->where('runnable_id', $session->id)->sole();
    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->output['text'])->toBe('Hello there!');
    expect($run->output['message_id'])->toBe($reply->id);
    expect($run->triggered_by)->toBe($owner->id);
});

it('excludes the just-sent user message from the prior-turn context', function () {
    WorkspaceAgent::fake(['reply one', 'reply two']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    app(SendAgentMessageAction::class)->execute($session, 'first message');
    app(SendAgentMessageAction::class)->execute($session, 'second message');

    // Two full turns persisted: user+assistant, user+assistant.
    expect($session->fresh()->messages)->toHaveCount(4);

    WorkspaceAgent::assertPrompted(function ($prompt) {
        return $prompt->prompt === 'second message';
    });
});

it('fails the run and rethrows when the provider call fails', function () {
    WorkspaceAgent::fake(function () {
        throw new RuntimeException('provider unavailable');
    });

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    expect(fn () => app(SendAgentMessageAction::class)->execute($session, 'hi'))
        ->toThrow(RuntimeException::class);

    $run = Run::where('runnable_type', 'agent_session')->where('runnable_id', $session->id)->sole();
    expect($run->status)->toBe(RunStatus::Failed);

    // The user's message is still recorded even though the reply failed.
    expect($session->fresh()->messages)->toHaveCount(1);
});

it('records the session user on the turn so remembered facts stay with that user', function () {
    WorkspaceAgent::fake(['ok']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    app(SendAgentMessageAction::class)->execute($session, 'hi');

    $run = $session->runs()->sole();
    expect($run->triggered_by)->toBe($owner->id);

    $remember = collect(app(ToolRegistry::class)->toolsFor($agent, $run))->first(fn ($tool) => $tool instanceof RememberTool);
    $remember->handle(new Request(['key' => 'salary', 'value' => 'private']));

    expect($agent->memories()->sole()->user_id)->toBe($owner->id);

    $otherUser = User::factory()->create();
    expect(app(SkillInjector::class)->instructionsFor($agent, $otherUser->id))->not->toContain('private');
    expect(app(SkillInjector::class)->instructionsFor($agent, $owner->id))->toContain('salary: private');
});

it('passes the agent temperature and generation settings to the provider', function () {
    WorkspaceAgent::fake(['ok']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create([
        'temperature' => 0.3,
        'settings' => ['max_tokens' => 500, 'max_steps' => 4, 'top_p' => 0.9],
    ]);
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    app(SendAgentMessageAction::class)->execute($session, 'hi');

    WorkspaceAgent::assertPrompted(fn ($prompt) => $prompt->agent->temperature() === 0.3
        && $prompt->agent->maxTokens() === 500
        && $prompt->agent->maxSteps() === 4
        && $prompt->agent->topP() === 0.9);
});

it('fails the run when the turn cannot be set up', function () {
    $this->mock(ToolRegistry::class, fn ($mock) => $mock->shouldReceive('toolsFor')->andThrow(new RuntimeException('tool setup broke')));

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    expect(fn () => app(SendAgentMessageAction::class)->execute($session, 'hi'))
        ->toThrow(RuntimeException::class, 'tool setup broke');

    expect($session->runs()->sole()->status)->toBe(RunStatus::Failed);
});

it('refuses a new message while the previous turn is still running', function () {
    WorkspaceAgent::fake(['ok']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    $inFlight = $session->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    $inFlight->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

    expect(fn () => app(SendAgentMessageAction::class)->execute($session, 'hi'))
        ->toThrow(RunStateException::class);

    // An abandoned turn doesn't lock the conversation forever.
    $inFlight->forceFill(['started_at' => now()->subMinutes(AgentRunner::TURN_STALE_AFTER_MINUTES + 1)])->save();

    expect(app(SendAgentMessageAction::class)->execute($session, 'hi')->content)->toBe('ok');
});

it('sends a bounded history that skips unanswered messages from failed turns', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    $session->messages()->create(['role' => AgentMessageRole::User, 'content' => 'first']);
    $session->messages()->create(['role' => AgentMessageRole::Assistant, 'content' => 'answer']);
    $session->messages()->create(['role' => AgentMessageRole::User, 'content' => 'failed turn']);
    $current = $session->messages()->create(['role' => AgentMessageRole::User, 'content' => 'current']);

    $history = (new WorkspaceAgent('', $session, $current->id))->messages();

    expect(collect($history)->pluck('content')->all())->toBe(['first', 'answer']);

    foreach (range(1, 40) as $i) {
        $session->messages()->create(['role' => AgentMessageRole::User, 'content' => "q{$i}"]);
        $session->messages()->create(['role' => AgentMessageRole::Assistant, 'content' => "a{$i}"]);
    }

    $history = collect((new WorkspaceAgent('', $session))->messages());

    expect($history)->toHaveCount(WorkspaceAgent::HISTORY_LIMIT)
        ->and($history->first()->role->value)->toBe('user')
        ->and($history->last()->content)->toBe('a40');
});

it('prompts using the resolved model catalog chain when the agent is opted in', function () {
    WorkspaceAgent::fake(['Hello there!']);

    $catalog = ModelCatalog::factory()->create(['slug' => 'claude-3-5-sonnet']);
    ModelRoute::factory()->forCatalog($catalog)->create([
        'execution_provider' => 'anthropic',
        'execution_model_id' => 'claude-3-5-sonnet-latest',
        'priority' => 0,
    ]);
    ModelRoute::factory()->forCatalog($catalog)->create([
        'execution_provider' => 'openrouter',
        'execution_model_id' => 'anthropic/claude-3.5-sonnet',
        'priority' => 1,
    ]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['model_catalog_id' => $catalog->id]);

    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);
    app(SendAgentMessageAction::class)->execute($session, 'Hi!');

    WorkspaceAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->provider->name() === 'anthropic' && $prompt->model === 'claude-3-5-sonnet-latest';
    });
});
