<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Agents\WorkspaceAgent;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

it('resolves the templated prompt and returns the agent reply as node output', function () {
    WorkspaceAgent::fake(['The answer is 42.']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'ask', 'type' => 'agent', 'config' => ['agent_id' => $agent->id, 'prompt' => 'Echo {{ input.question }}']],
        ],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['question' => 'life, the universe, and everything']);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    $nodeRun = $run->nodeRuns->firstWhere('key', 'ask');
    expect($nodeRun->status)->toBe(NodeRunStatus::Completed);
    expect($nodeRun->output['text'])->toBe('The answer is 42.');
    expect($nodeRun->usage)->not->toBeNull();

    WorkspaceAgent::assertPrompted('Echo life, the universe, and everything');
});

it('defaults the prompt to input.message when config.prompt is omitted', function () {
    WorkspaceAgent::fake(['ok']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => ['agent_id' => $agent->id]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    app(StartWorkflowRunAction::class)->execute($workflow, ['message' => 'hello from input']);

    WorkspaceAgent::assertPrompted('hello from input');
});

it('feeds the node usage into the credit ledger like any other node', function () {
    WorkspaceAgent::fake(['ok']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => ['agent_id' => $agent->id, 'prompt' => 'hi']]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $nodeRun = $run->fresh(['nodeRuns'])->nodeRuns->first();

    $transaction = CreditTransaction::where('source_id', $nodeRun->id)->sole();
    expect($transaction->credits)->toBeGreaterThanOrEqual(1);
});

it('charges the 3-credit agent node surcharge on top of the base credit', function () {
    WorkspaceAgent::fake(['ok']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => ['agent_id' => $agent->id, 'prompt' => 'hi']]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $nodeRun = $run->fresh(['nodeRuns'])->nodeRuns->first();

    // 1 base + 3 agent surcharge; no usage tokens on the faked reply.
    expect(CreditTransaction::where('source_id', $nodeRun->id)->sole()->credits)->toBe(4);
});

it('starts a fresh conversation and returns its id, full history, and attachment names', function () {
    WorkspaceAgent::fake(['Hi there!']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => ['agent_id' => $agent->id, 'prompt' => 'hi']]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $output = $run->fresh(['nodeRuns'])->nodeRuns->first()->output;

    expect($output['conversation_id'])->toBeString();
    expect(AgentSession::find($output['conversation_id']))->not->toBeNull();
    expect($output['messages'])->toBe([
        ['role' => 'user', 'content' => 'hi', 'tool_calls' => null],
        ['role' => 'assistant', 'content' => 'Hi there!', 'tool_calls' => null],
    ]);
    expect($output['attachment_names'])->toBe('');
});

it('continues a previous conversation instead of starting a new one', function () {
    WorkspaceAgent::fake(['first reply', 'second reply']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => ['agent_id' => $agent->id, 'prompt' => 'first message']]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $firstRun = app(StartWorkflowRunAction::class)->execute($workflow);
    $conversationId = $firstRun->fresh(['nodeRuns'])->nodeRuns->first()->output['conversation_id'];

    $workflow->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => [
            'agent_id' => $agent->id,
            'prompt' => 'second message',
            'previous_conversation_id' => $conversationId,
        ]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $secondRun = app(StartWorkflowRunAction::class)->execute($workflow->fresh());
    $output = $secondRun->fresh(['nodeRuns'])->nodeRuns->first()->output;

    expect($output['conversation_id'])->toBe($conversationId);
    expect(AgentSession::count())->toBe(1);
    expect($output['messages'])->toBe([
        ['role' => 'user', 'content' => 'first message', 'tool_calls' => null],
        ['role' => 'assistant', 'content' => 'first reply', 'tool_calls' => null],
        ['role' => 'user', 'content' => 'second message', 'tool_calls' => null],
        ['role' => 'assistant', 'content' => 'second reply', 'tool_calls' => null],
    ]);
});

it('fails the node when the previous conversation id does not belong to this agent', function () {
    WorkspaceAgent::fake(['ok']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $otherAgent = Agent::factory()->forWorkspace($workspace)->create();
    $foreignSession = AgentSession::factory()->create(['workspace_id' => $workspace->id, 'agent_id' => $otherAgent->id]);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => [
            'agent_id' => $agent->id,
            'prompt' => 'hi',
            'previous_conversation_id' => $foreignSession->id,
        ]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->nodeRuns->first()->status)->toBe(NodeRunStatus::Failed);
});

it('fails the node when the referenced agent does not belong to the workflow\'s workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $foreignAgent = Agent::factory()->forWorkspace($otherWorkspace)->create();

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'ask', 'type' => 'agent', 'config' => ['agent_id' => $foreignAgent->id, 'prompt' => 'hi']]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->nodeRuns->first()->status)->toBe(NodeRunStatus::Failed);
});
