<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Broadcasting\Channels;
use App\Broadcasting\WorkspaceChannelGate;
use App\Enums\RunStatus;
use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Events\Runs\NodeRunStateChanged;
use App\Events\Runs\RunStateChanged;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Event;

it('broadcasts run and node-run state changes while a workflow executes', function () {
    Event::fake([RunStateChanged::class, NodeRunStateChanged::class]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['x' => 1]);

    Event::assertDispatched(
        RunStateChanged::class,
        fn (RunStateChanged $event) => $event->run->id === $run->id,
    );
    Event::assertDispatched(
        NodeRunStateChanged::class,
        fn (NodeRunStateChanged $event) => $event->nodeRun->key === 'a',
    );
});

it('puts run events on the workspace and per-run private channels', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $channels = array_map(fn ($channel) => (string) $channel, (new RunStateChanged($run))->broadcastOn());

    expect($channels)->toBe([
        'private-'.Channels::workspaceRuns($workspace->id),
        'private-'.Channels::run($run),
    ]);

    $payload = (new RunStateChanged($run))->broadcastWith();
    expect($payload['id'])->toBe($run->id);
    expect($payload['status'])->toBe('completed');

    $later = (new RunStateChanged($run))->broadcastWith();
    expect($payload['emitted_at'])->toBeInt()
        ->and($later['emitted_at'])->toBeGreaterThanOrEqual($payload['emitted_at']);
});

it('keeps node-run payloads off the workspace-wide channel and free of output', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['x' => 'secretish']);
    $nodeRun = $run->fresh(['nodeRuns'])->nodeRuns->first();

    $event = new NodeRunStateChanged($nodeRun);

    expect(array_map(fn ($channel) => (string) $channel, $event->broadcastOn()))
        ->toBe(['private-'.Channels::run($run)]);
    expect($event->broadcastWith())->not->toHaveKey('output')
        ->and($event->broadcastWith()['emitted_at'])->toBeInt();
    expect($event->broadcastAs())->toBe('node-run.state-changed');
});

it('authorizes run channels only for workspace members who can view runs', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    $gate = app(WorkspaceChannelGate::class);

    expect($gate->runs($owner, $workspace->id))->toBeTrue();
    expect($gate->run($owner, $workspace->id, $run->id))->toBeTrue();
    expect($gate->runs($stranger, $workspace->id))->toBeFalse();
    expect($gate->run($stranger, $workspace->id, $run->id))->toBeFalse();
});

it('refuses a run channel whose run belongs to another workspace', function () {
    $owner = User::factory()->create();
    $first = app(WorkspaceService::class)->create($owner, ['name' => 'First']);
    $second = app(WorkspaceService::class)->create($owner, ['name' => 'Second']);

    $workflow = Workflow::factory()->forWorkspace($second)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    expect(app(WorkspaceChannelGate::class)->run($owner, $first->id, $run->id))->toBeFalse();
});

it('authorizes agent session channels for members and refuses outsiders', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = AgentSession::factory()->forAgent($agent)->create();

    $gate = app(WorkspaceChannelGate::class);

    expect($gate->agentSession($owner, $workspace->id, $session->id))->toBeTrue();
    expect($gate->agentSession($stranger, $workspace->id, $session->id))->toBeFalse();
    expect(Role::Viewer->has(Permission::AgentView))->toBeTrue();
});

it('puts the broadcasting auth endpoint behind the api guard', function () {
    // Registered explicitly in bootstrap/app.php so it sits on the Passport
    // guard rather than the session-based web guard this API never uses.
    $this->postJson('/api/broadcasting/auth', [
        'channel_name' => 'private-workspaces.1.runs',
        'socket_id' => '1234.5678',
    ])->assertUnauthorized();
});

it('broadcasts the state a run was in when the transition happened', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    // Built while the run was still running, delivered after it finished:
    // the queued-broadcast case. The payload must describe the transition it
    // was created for, not whatever the row says later.
    $running = $run->fresh();
    $running->forceFill(['status' => RunStatus::Running])->syncOriginal();
    $event = new RunStateChanged($running);

    $run->forceFill(['status' => RunStatus::Failed])->save();

    expect($event->broadcastWith()['status'])->toBe('running');
});
