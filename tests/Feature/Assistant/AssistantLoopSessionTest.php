<?php

use App\Ai\Agents\Assistant\AssistantAgent;
use App\Enums\Assistant\AssistantActionStatus;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantAction;
use App\Models\Assistant\AssistantQueuedInput;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTurn;
use App\Models\User;
use App\Services\Assistant\Runtime\AssistantLoop;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Priya']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id, 'title' => null]);
    $this->loop = app(AssistantLoop::class);
});

it('lets a message start a new turn when the running one is older than the stale limit', function () {
    AssistantAgent::fake(['Back again.']);

    $dead = AssistantTurn::factory()->running()->create([
        'assistant_session_id' => $this->session->id,
        'created_at' => now()->subMinutes((int) config('assistant.runtime.turn_stale_after_minutes') + 1),
    ]);

    $result = $this->loop->send($this->session, 'Anyone there?');

    expect($result)->toBeInstanceOf(AssistantTurn::class)
        ->and($result->is($dead))->toBeFalse()
        ->and($result->refresh()->status)->toBe(AssistantTurnStatus::Completed)
        ->and($this->session->queuedInputs()->count())->toBe(0);
});

it('queues a message while a recent turn is still running', function () {
    AssistantAgent::fake(['Never used.']);

    AssistantTurn::factory()->running()->create(['assistant_session_id' => $this->session->id]);

    $result = $this->loop->send($this->session, 'Second thought');

    expect($result)->toBeInstanceOf(AssistantQueuedInput::class)
        ->and($result->content)->toBe('Second thought')
        ->and($result->consumed_at)->toBeNull();
});

it('does not start a queued message while a turn waits for approval', function () {
    AssistantAgent::fake(['Never used.']);

    AssistantTurn::factory()->create([
        'assistant_session_id' => $this->session->id,
        'status' => AssistantTurnStatus::AwaitingApproval,
    ]);
    $queued = $this->session->queuedInputs()->create(['content' => 'waiting its turn']);

    expect($this->loop->startNextQueued($this->session))->toBeNull()
        ->and($queued->refresh()->consumed_at)->toBeNull();
});

it('starts nothing when no message is queued', function () {
    expect($this->loop->startNextQueued($this->session))->toBeNull();
});

it('consumes queued messages oldest first, one turn at a time', function () {
    AssistantAgent::fake(['One.', 'Two.']);

    $first = $this->session->queuedInputs()->create(['content' => 'first']);
    $first->forceFill(['created_at' => now()->subMinute()])->save();
    $second = $this->session->queuedInputs()->create(['content' => 'second']);

    $turn = $this->loop->startNextQueued($this->session);

    expect($turn->userMessage->content)->toBe('first')
        ->and($first->refresh()->consumed_at)->not->toBeNull()
        ->and($second->refresh()->consumed_at)->toBeNull();
});

it('expires the calls of a paused turn when it is cancelled', function () {
    $turn = AssistantTurn::factory()->create([
        'assistant_session_id' => $this->session->id,
        'status' => AssistantTurnStatus::AwaitingApproval,
    ]);
    $action = AssistantAction::factory()->create(['assistant_turn_id' => $turn->id]);

    $this->loop->cancel($turn);

    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::Cancelled)
        ->and($action->refresh()->status)->toBe(AssistantActionStatus::Expired);
});
