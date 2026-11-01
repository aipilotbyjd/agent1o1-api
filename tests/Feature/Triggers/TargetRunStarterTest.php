<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Enums\RunStatus;
use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerService;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;

function workspaceFor(User $owner, string $name = 'Acme'): Workspace
{
    return app(WorkspaceService::class)->create($owner, ['name' => $name]);
}

function queuedEventFor(Trigger $trigger, array $payload = ['a' => 1]): TriggerEvent
{
    return $trigger->events()->create([
        'source' => $trigger->type,
        'status' => TriggerEventStatus::Queued,
        'payload' => $payload,
        'delivery_id' => 'd-'.fake()->uuid(),
    ]);
}

it('starts a run for the trigger\'s workflow and points the event at it', function () {
    $owner = User::factory()->create();
    $workflow = Workflow::factory()->forWorkspace(workspaceFor($owner))->published()->create();
    $trigger = Trigger::factory()->webhook()->forWorkflow($workflow)->create(['created_by' => $owner->id]);
    $event = queuedEventFor($trigger, ['issue' => ['number' => 7]]);

    (new FireTriggerEvent($trigger, $event))->handle(app(TriggerService::class));

    $event->refresh();
    $run = Run::findOrFail($event->run_id);

    expect($event->status)->toBe(TriggerEventStatus::Fired);
    expect($run->workflow_id)->toBe($workflow->id);
    expect($run->workflow_version_id)->toBe($workflow->current_version_id);
    // The payload is the run's input, and the run is attributed to whoever
    // created the trigger.
    expect($run->input)->toBe(['issue' => ['number' => 7]]);
    expect($run->trigger_type)->toBe(TriggerType::Webhook->value);
    expect($run->triggered_by)->toBe($owner->id);
    expect($trigger->fresh()->last_run_at)->not->toBeNull();
});

it('skips intake for an unpublished workflow instead of queueing a run', function () {
    $owner = User::factory()->create();
    $workflow = Workflow::factory()->forWorkspace(workspaceFor($owner))->create();
    $trigger = Trigger::factory()->webhook()->forWorkflow($workflow)->create();

    $event = app(TriggerService::class)->receive($trigger, TriggerType::Webhook, ['a' => 1], 'd-1');

    expect($event->status)->toBe(TriggerEventStatus::Skipped);
    expect($event->error)->toBe('Target is not runnable.');
    expect(Run::count())->toBe(0);
});

it('refuses a target that belongs to another workspace', function () {
    $owner = User::factory()->create();
    $theirWorkflow = Workflow::factory()->forWorkspace(workspaceFor($owner, 'Theirs'))->published()->create();

    // Same target id, different workspace on the trigger itself: the shape a
    // tampered-with or moved target leaves behind.
    $trigger = Trigger::factory()
        ->webhook()
        ->forWorkspace(workspaceFor(User::factory()->create(), 'Ours'))
        ->create(['target_id' => $theirWorkflow->id]);

    $event = app(TriggerService::class)->receive($trigger, TriggerType::Webhook, [], 'd-1');

    expect($event->status)->toBe(TriggerEventStatus::Skipped);
    expect(Run::count())->toBe(0);
});

it('refuses a soft-deleted target', function () {
    $owner = User::factory()->create();
    $workflow = Workflow::factory()->forWorkspace(workspaceFor($owner))->published()->create();
    $trigger = Trigger::factory()->webhook()->forWorkflow($workflow)->create();

    $workflow->delete();

    expect(app(TriggerService::class)->canRun($trigger))->toBeFalse();
});

it('reports a workflow with an in-flight run as already running', function () {
    $owner = User::factory()->create();
    $workflow = Workflow::factory()->forWorkspace(workspaceFor($owner))->published()->create();
    $trigger = Trigger::factory()->forWorkflow($workflow)->create();
    $triggers = app(TriggerService::class);

    $run = Run::factory()->forWorkflow($workflow)->create(['status' => RunStatus::Running]);

    expect($triggers->isAlreadyRunning($trigger))->toBeTrue();

    $run->forceFill(['status' => RunStatus::Completed])->save();

    expect($triggers->isAlreadyRunning($trigger))->toBeFalse();
});

it('starts an agent session and turn for an agent trigger', function () {
    WorkspaceAgent::fake(['On it.']);

    $owner = User::factory()->create();
    $agent = Agent::factory()->forWorkspace(workspaceFor($owner))->create();
    $trigger = Trigger::factory()->webhook()->forAgent($agent)->create(['created_by' => $owner->id]);
    $event = queuedEventFor($trigger, ['ticket' => 'SUP-1']);

    (new FireTriggerEvent($trigger, $event))->handle(app(TriggerService::class));

    $event->refresh();
    $run = Run::findOrFail($event->run_id);
    $session = AgentSession::where('agent_id', $agent->id)->sole();

    expect($event->status)->toBe(TriggerEventStatus::Fired);
    expect($run->runnable_type)->toBe('agent_session');
    expect($run->runnable_id)->toBe($session->id);
    expect($run->trigger_type)->toBe(TriggerType::Webhook->value);
    expect($run->status)->toBe(RunStatus::Completed);

    // No `config.message` template, so the raw payload is handed to the agent.
    expect($session->messages()->first()->content)->toContain('SUP-1');
});

it('prompts a triggered agent with the configured message template', function () {
    WorkspaceAgent::fake(['Triaged.']);

    $owner = User::factory()->create();
    $agent = Agent::factory()->forWorkspace(workspaceFor($owner))->create();
    $trigger = Trigger::factory()->webhook()->forAgent($agent)->create([
        'config' => ['message' => 'Triage issue: {{ payload.issue.title }}'],
    ]);

    (new FireTriggerEvent($trigger, queuedEventFor($trigger, ['issue' => ['title' => 'Login broken']])))
        ->handle(app(TriggerService::class));

    $session = AgentSession::where('agent_id', $agent->id)->sole();

    expect($session->messages()->first()->content)->toBe('Triage issue: Login broken');
});

it('fires agent events on the agent queue and workflow events on the event queue', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = workspaceFor($owner);
    $workflow = Workflow::factory()->forWorkspace($workspace)->published()->create();
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $triggers = app(TriggerService::class);
    $triggers->receive(Trigger::factory()->webhook()->forWorkflow($workflow)->create(), TriggerType::Webhook, [], 'd-1');
    $triggers->receive(Trigger::factory()->webhook()->forAgent($agent)->create(), TriggerType::Webhook, [], 'd-2');

    Queue::assertPushed(
        FireTriggerEvent::class,
        fn (FireTriggerEvent $job): bool => $job->queue === config('triggers.fire_queue')
            && $job->timeout === config('triggers.fire_timeout_seconds'),
    );

    // An LLM call must never sit on the latency-sensitive event queue.
    Queue::assertPushed(
        FireTriggerEvent::class,
        fn (FireTriggerEvent $job): bool => $job->queue === config('triggers.agent_fire_queue')
            && $job->timeout === config('triggers.agent_fire_timeout_seconds'),
    );
});
