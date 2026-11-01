<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Broadcasting\Channels;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\RunStatus;
use App\Events\Agents\AgentTurnChanged;
use App\Events\Agents\AgentTurnDelta;
use App\Events\Agents\AgentTurnToolActivity;
use App\Jobs\Agents\RunAgentTurnJob;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\AgentRunner;
use App\Services\Agents\AgentTurnBroadcaster;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create();
    $this->session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id]);

    $this->turnsUrl = "/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/sessions/{$this->session->id}/turns";

    Passport::actingAs($this->owner);
});

it('opens the turn, queues it and returns before the model answers', function () {
    Queue::fake();

    $response = $this->postJson($this->turnsUrl, ['message' => 'Hi!'])
        ->assertAccepted()
        ->assertJsonPath('data.turn.status', 'running');

    $run = Run::query()->findOrFail($response->json('data.turn.run_id'));

    Queue::assertPushed(RunAgentTurnJob::class, fn (RunAgentTurnJob $job): bool => $job->runId === $run->id);

    expect($run->status)->toBe(RunStatus::Running);
    expect($run->input['user_message_id'])->toBe($response->json('data.turn.user_message_id'));
    expect($this->session->messages()->sole()->content)->toBe('Hi!');
    expect($this->session->messages()->where('role', AgentMessageRole::Assistant)->exists())->toBeFalse();
});

it('streams the reply over the conversation channel and persists it', function () {
    Event::fake([AgentTurnChanged::class, AgentTurnDelta::class]);
    WorkspaceAgent::fake(['Streaming hello!']);

    $this->postJson($this->turnsUrl, ['message' => 'Hi!'])->assertAccepted();

    $reply = $this->session->messages()->where('role', AgentMessageRole::Assistant)->sole();
    expect($reply->content)->toBe('Streaming hello!');

    $run = Run::query()->where('runnable_id', $this->session->id)->where('runnable_type', 'agent_session')->sole();
    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->output['message_id'])->toBe($reply->id);

    $channel = Channels::agentSession($this->session);

    Event::assertDispatched(AgentTurnDelta::class, function (AgentTurnDelta $event) use ($channel, $run): bool {
        return $event->broadcastOn() == new PrivateChannel($channel)
            && $event->broadcastWith() === ['run_id' => $run->id, 'text' => 'Streaming hello!'];
    });

    $statuses = Event::dispatched(AgentTurnChanged::class)->map(fn (array $args): string => $args[0]->broadcastWith()['turn']['status']);
    expect($statuses->all())->toBe(['running', 'completed']);

    Event::assertDispatched(AgentTurnChanged::class, fn (AgentTurnChanged $event): bool => ($event->broadcastWith()['turn']['message_id'] ?? null) === $reply->id);
});

it('batches reply text into chunks instead of one event per token', function () {
    Event::fake([AgentTurnDelta::class]);
    WorkspaceAgent::fake([str_repeat('word ', 200)]);

    $this->postJson($this->turnsUrl, ['message' => 'Go on.'])->assertAccepted();

    $deltas = Event::dispatched(AgentTurnDelta::class)->map(fn (array $args): string => $args[0]->text);

    expect($deltas->implode(''))->toBe(str_repeat('word ', 200));
    expect($deltas->count())->toBeLessThan(200);
    $deltas->each(fn (string $text) => expect(strlen(json_encode(['text' => $text])))->toBeLessThan(10_000));
});

it('broadcasts each tool call with its small arguments and output, and keeps the full call on the stored reply', function () {
    Event::fake([AgentTurnToolActivity::class]);
    WorkspaceAgent::fake([
        new ToolCall('call-1', 'remember', ['key' => 'preferred_name', 'value' => 'JD']),
        'Got it, JD!',
    ]);

    $this->postJson($this->turnsUrl, ['message' => 'Call me JD.'])->assertAccepted();

    Event::assertDispatched(AgentTurnToolActivity::class, fn (AgentTurnToolActivity $event): bool => $event->phase === AgentTurnToolActivity::STARTED
        && $event->broadcastWith()['tool'] === 'remember'
        && $event->broadcastWith()['arguments'] === ['key' => 'preferred_name', 'value' => 'JD']);

    Event::assertDispatched(AgentTurnToolActivity::class, function (AgentTurnToolActivity $event): bool {
        $payload = $event->broadcastWith();

        return $event->phase === AgentTurnToolActivity::FINISHED
            && $payload['tool_call_id'] === 'call-1'
            && $payload['successful'] === true
            && $payload['output'] === 'Remembered preferred_name.';
    });

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/sessions/{$this->session->id}")
        ->assertOk()
        ->assertJsonPath('data.session.messages.1.tool_results.0', [
            'id' => 'call-1',
            'name' => 'remember',
            'output' => 'Remembered preferred_name.',
        ]);
});

it('leaves oversized tool arguments and output off the wire', function () {
    Event::fake([AgentTurnToolActivity::class]);
    WorkspaceAgent::fake([
        new ToolCall('call-1', 'remember', ['key' => 'notes', 'value' => str_repeat('x', 5000)]),
        'Saved.',
    ]);

    $this->postJson($this->turnsUrl, ['message' => 'Remember this.'])->assertAccepted();

    Event::assertDispatched(AgentTurnToolActivity::class, fn (AgentTurnToolActivity $event): bool => $event->phase === AgentTurnToolActivity::STARTED
        && ! array_key_exists('arguments', $event->broadcastWith()));

    Event::dispatched(AgentTurnToolActivity::class)->each(fn (array $args) => expect(strlen(json_encode($args[0]->broadcastWith())))->toBeLessThan(10_000));
});

it('marks the turn failed and tells the chat when the provider blows up', function () {
    Event::fake([AgentTurnChanged::class]);
    WorkspaceAgent::fake(function () {
        throw new RuntimeException('provider unavailable');
    });

    $this->postJson($this->turnsUrl, ['message' => 'Hi!'])->assertAccepted();

    $run = Run::query()->where('runnable_id', $this->session->id)->where('runnable_type', 'agent_session')->sole();
    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->error)->toContain('provider unavailable');

    Event::assertDispatched(AgentTurnChanged::class, function (AgentTurnChanged $event): bool {
        $turn = $event->broadcastWith()['turn'];

        // The raw provider message is on the run, never on the wire.
        return $turn['status'] === 'failed'
            && $turn['error'] === 'Something went wrong while answering. Please try again.'
            && ! str_contains(json_encode($turn), 'provider unavailable');
    });
});

it('hands uploaded attachments to the queued turn', function () {
    Queue::fake();

    $response = $this->post($this->turnsUrl, [
        'message' => 'Summarise this.',
        'attachments' => [UploadedFile::fake()->createWithContent('notes.txt', 'Quarterly numbers')],
    ], ['Accept' => 'application/json'])->assertAccepted();

    $run = Run::query()->findOrFail($response->json('data.turn.run_id'));

    expect($run->input['attachment_ids'])->toHaveCount(1);

    WorkspaceAgent::fake(['Done.']);
    Queue::pushed(RunAgentTurnJob::class)->each(fn (RunAgentTurnJob $job) => $job->handle(app(AgentRunner::class), app(AgentTurnBroadcaster::class)));

    expect($run->fresh()->status)->toBe(RunStatus::Completed);
});

it('refuses a second message while the conversation is still answering', function () {
    Queue::fake();

    $this->postJson($this->turnsUrl, ['message' => 'One.'])->assertAccepted();

    $this->postJson($this->turnsUrl, ['message' => 'Two.'])->assertStatus(409);

    Queue::assertPushed(RunAgentTurnJob::class, 1);
});

it('does not run a turn twice', function () {
    Queue::fake();
    WorkspaceAgent::fake(['Only once.']);

    $runId = $this->postJson($this->turnsUrl, ['message' => 'Hi!'])->json('data.turn.run_id');

    $job = new RunAgentTurnJob($runId);
    $job->handle(app(AgentRunner::class), app(AgentTurnBroadcaster::class));
    $job->handle(app(AgentRunner::class), app(AgentTurnBroadcaster::class));

    expect($this->session->messages()->where('role', AgentMessageRole::Assistant)->count())->toBe(1);
});

it('fails the run and tells the chat when the worker dies mid-turn', function () {
    Queue::fake();
    Event::fake([AgentTurnChanged::class]);

    $runId = $this->postJson($this->turnsUrl, ['message' => 'Hi!'])->json('data.turn.run_id');

    (new RunAgentTurnJob($runId))->failed(new RuntimeException('Job timed out'));

    expect(Run::query()->findOrFail($runId)->status)->toBe(RunStatus::Failed);

    Event::assertDispatched(AgentTurnChanged::class, fn (AgentTurnChanged $event): bool => $event->broadcastWith()['turn']['status'] === 'failed');
});

it('404s sending a turn into a session that belongs to another agent', function () {
    $other = Agent::factory()->forWorkspace($this->workspace)->create();
    $session = $other->sessions()->create(['workspace_id' => $this->workspace->id]);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/sessions/{$session->id}/turns", ['message' => 'Hi!'])
        ->assertNotFound();
});

it('replaces a retried message and everything after it', function () {
    Queue::fake();

    $first = $this->session->messages()->create(['role' => AgentMessageRole::User, 'content' => 'Keep me']);
    $this->session->messages()->create(['role' => AgentMessageRole::Assistant, 'content' => 'Kept reply']);
    $retried = $this->session->messages()->create(['role' => AgentMessageRole::User, 'content' => 'Summarise this']);
    $this->session->messages()->create(['role' => AgentMessageRole::Assistant, 'content' => 'A reply being replaced']);

    $this->postJson($this->turnsUrl, ['message' => 'Summarise this', 'replaces_message_id' => $retried->id])
        ->assertAccepted();

    $contents = $this->session->messages()->oldest('id')->pluck('content')->all();
    expect($contents)->toBe(['Keep me', 'Kept reply', 'Summarise this'])
        ->and($this->session->messages()->whereKey($retried->id)->exists())->toBeFalse()
        ->and($this->session->messages()->whereKey($first->id)->exists())->toBeTrue();
});

it('refuses to replace a message from another conversation, or a reply', function () {
    Queue::fake();

    $other = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id]);
    $elsewhere = $other->messages()->create(['role' => AgentMessageRole::User, 'content' => 'Not yours']);
    $reply = $this->session->messages()->create(['role' => AgentMessageRole::Assistant, 'content' => 'A reply']);

    $this->postJson($this->turnsUrl, ['message' => 'Hi', 'replaces_message_id' => $elsewhere->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('replaces_message_id');
    $this->postJson($this->turnsUrl, ['message' => 'Hi', 'replaces_message_id' => $reply->id])
        ->assertUnprocessable();

    expect($other->messages()->count())->toBe(1)
        ->and($this->session->messages()->count())->toBe(1);
});
