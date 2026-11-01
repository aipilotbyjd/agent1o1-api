<?php

use App\Actions\Workflows\Builder\SendWorkflowBuilderMessageAction;
use App\Ai\Agents\WorkflowBuilderAgent;
use App\Ai\Agents\WorkflowBuilderTitleAgent;
use App\Ai\Tools\WorkflowBuilder\SubmitWorkflowTitleTool;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Workflows\BuilderMessageStatus;
use App\Events\Workflows\WorkflowBuilderActivity;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Responses\Data\ToolCall;

function builderJobSession(array $attributes = []): WorkflowBuilderSession
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create($attributes);
}

it('writes the reply, what it changed, and the draft version it produced', function () {
    $session = builderJobSession(['title' => 'Signup Alerts']);

    // Stands in for the agent's own tool calls, which edit the same draft.
    WorkflowBuilderAgent::fake(function () use ($session): string {
        WorkflowBuilderSession::find($session->id)->addNode('notify', 'transform', ['mapping' => []]);

        return 'Added a notify step.';
    });

    $reply = app(SendWorkflowBuilderMessageAction::class)->execute($session, 'Notify me on signup')->fresh();

    expect($reply->processing_status)->toBe(BuilderMessageStatus::Completed);
    expect($reply->content)->toBe('Added a notify step.');
    expect($reply->actions)->toBe([['type' => 'node_added', 'key' => 'notify', 'node_type' => 'transform']]);
    expect($reply->draft_version_id)->toBe($session->draftVersions()->value('id'));
});

it('tells the user when the agent runs out of steps mid-build', function () {
    $toolCalls = array_map(fn (int $i) => new ToolCall("call_{$i}", 'read_draft', []), range(1, 100));
    WorkflowBuilderAgent::fake($toolCalls);
    $session = builderJobSession(['title' => 'Signup Alerts']);

    $reply = app(SendWorkflowBuilderMessageAction::class)->execute($session, 'Build a big flow')->fresh();

    expect($reply->processing_status)->toBe(BuilderMessageStatus::Completed);
    expect($reply->content)->toBe(ProcessWorkflowBuilderMessageJob::OUT_OF_STEPS_NOTE);
});

it('does not add the out-of-steps note to a turn that finished', function () {
    WorkflowBuilderAgent::fake([new ToolCall('call_1', 'read_draft', []), 'All done.']);
    $session = builderJobSession(['title' => 'Signup Alerts']);

    $reply = app(SendWorkflowBuilderMessageAction::class)->execute($session, 'hello')->fresh();

    expect($reply->content)->toBe('All done.');
});

it('charges the turn once, against its reply', function () {
    WorkflowBuilderAgent::fake(['Done.']);
    $session = builderJobSession(['title' => 'Signup Alerts']);

    $reply = app(SendWorkflowBuilderMessageAction::class)->execute($session, 'hello');

    $charge = CreditTransaction::query()->where('source_type', CreditTransactionType::WorkflowBuilder)->sole();
    expect($charge->source_id)->toBe($reply->id);
    expect($charge->credits)->toBeGreaterThan(0);
});

it('titles an untitled session from its first message', function () {
    WorkflowBuilderAgent::fake(['Done.']);
    WorkflowBuilderTitleAgent::fake([new ToolCall('call_1', SubmitWorkflowTitleTool::NAME, ['title' => 'Signup Slack Alert'])]);
    $session = builderJobSession();

    app(SendWorkflowBuilderMessageAction::class)->execute($session, 'When someone signs up, post it to Slack');

    expect($session->fresh()->title)->toBe('Signup Slack Alert');
});

it('keeps the default title when titling fails, without failing the turn', function () {
    WorkflowBuilderAgent::fake(['Done.']);
    WorkflowBuilderTitleAgent::fake(['no tool call here']);
    $session = builderJobSession();

    $reply = app(SendWorkflowBuilderMessageAction::class)->execute($session, 'hello')->fresh();

    expect($reply->processing_status)->toBe(BuilderMessageStatus::Completed);
    expect($session->fresh()->title)->toBe(WorkflowBuilderSession::DEFAULT_TITLE);
});

it('marks a failed turn failed, keeps its draft edits, and does not charge it', function () {
    $session = builderJobSession(['title' => 'Signup Alerts']);

    WorkflowBuilderAgent::fake(function () use ($session): never {
        WorkflowBuilderSession::find($session->id)->addNode('half_done', 'transform', ['mapping' => []]);

        throw new RuntimeException('provider exploded');
    });

    $reply = app(SendWorkflowBuilderMessageAction::class)->execute($session, 'hello')->fresh();

    expect($reply->processing_status)->toBe(BuilderMessageStatus::Failed);
    expect($reply->error_message)->toBe(ProcessWorkflowBuilderMessageJob::FAILURE_MESSAGE);
    expect($reply->actions)->toBe([['type' => 'node_added', 'key' => 'half_done', 'node_type' => 'transform']]);
    expect($session->fresh()->currentGraph()['nodes'])->toHaveCount(1);
    expect(CreditTransaction::query()->where('source_type', CreditTransactionType::WorkflowBuilder)->count())->toBe(0);

    // The session isn't left blocked by the failed reply.
    expect($session->hasReplyInFlight())->toBeFalse();
});

it('leaves failed replies out of the conversation history', function () {
    WorkflowBuilderAgent::fake(['second reply']);
    $session = builderJobSession(['title' => 'Signup Alerts']);
    $session->messages()->create(['role' => 'user', 'content' => 'first']);
    $session->messages()->create(['role' => 'assistant', 'content' => 'partial', 'processing_status' => BuilderMessageStatus::Failed]);

    $history = collect((new WorkflowBuilderAgent($session))->messages())->map(fn ($message) => $message->content)->all();

    expect($history)->toBe(['first']);
});

it('broadcasts the reply status as it moves', function () {
    Event::fake([WorkflowBuilderActivity::class]);
    WorkflowBuilderAgent::fake(['Done.']);
    $session = builderJobSession(['title' => 'Signup Alerts']);

    app(SendWorkflowBuilderMessageAction::class)->execute($session, 'hello');

    $statuses = collect(Event::dispatched(WorkflowBuilderActivity::class))
        ->map(fn (array $dispatch) => $dispatch[0])
        ->filter(fn (WorkflowBuilderActivity $event) => $event->type === 'status')
        ->map(fn (WorkflowBuilderActivity $event) => $event->payload['status'])
        ->values()
        ->all();

    expect($statuses)->toBe(['processing', 'completed']);
});

it('caps oversized broadcast payloads under the socket limit', function () {
    $session = builderJobSession();

    $event = new WorkflowBuilderActivity($session, 'message-id', 'tool-result', [
        'output' => str_repeat('x', 50_000),
        'arguments' => ['blob' => str_repeat('y', 50_000)],
    ]);

    expect($event->broadcastAs())->toBe('builder.tool-result');
    expect(strlen(json_encode($event->broadcastWith())))->toBeLessThan(10_000);
});

it('does nothing for a reply that is no longer pending', function () {
    WorkflowBuilderAgent::fake(['should not run']);
    $session = builderJobSession(['title' => 'Signup Alerts']);
    $user = $session->messages()->create(['role' => 'user', 'content' => 'hello']);
    $reply = $session->messages()->create(['role' => 'assistant', 'content' => 'already written']);

    ProcessWorkflowBuilderMessageJob::dispatchSync($session->id, $user->id, $reply->id);

    expect($reply->fresh()->content)->toBe('already written');
    WorkflowBuilderAgent::assertNeverPrompted();
});
