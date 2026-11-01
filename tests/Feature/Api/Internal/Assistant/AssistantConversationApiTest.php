<?php

use App\Ai\Assistant\AssistantAgent;
use App\Broadcasting\WorkspaceChannelGate;
use App\Enums\Assistant\AssistantActionStatus;
use App\Enums\Assistant\AssistantFollowUpStatus;
use App\Enums\Assistant\AssistantToolRule;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Enums\Workspaces\Role;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantAction;
use App\Models\Assistant\AssistantFollowUp;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTurn;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id]);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/assistant";
    $this->sessionUrl = "{$this->base}/sessions/{$this->session->id}";

    Passport::actingAs($this->owner);
});

it('sends a message and runs the turn', function () {
    AssistantAgent::fake(['Hi!']);

    $this->postJson("{$this->sessionUrl}/messages", ['content' => 'Hello'])
        ->assertAccepted()
        ->assertJsonPath('data.queued', false)
        ->assertJsonPath('data.turn.status', 'completed');

    $this->getJson("{$this->sessionUrl}/messages")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.content', 'Hi!');
});

it('queues a message while a turn is running', function () {
    AssistantTurn::factory()->running()->create(['assistant_session_id' => $this->session->id]);

    $this->postJson("{$this->sessionUrl}/messages", ['content' => 'Also this'])
        ->assertAccepted()
        ->assertJsonPath('data.queued', true)
        ->assertJsonPath('data.turn', null);

    $this->getJson($this->sessionUrl)
        ->assertSuccessful()
        ->assertJsonPath('data.active_turn.status', 'running')
        ->assertJsonPath('data.queued_count', 1);
});

it('requires message content', function () {
    $this->postJson("{$this->sessionUrl}/messages", ['content' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('content');
});

it('returns the paused turn with its waiting actions', function () {
    $turn = AssistantTurn::factory()->create(['assistant_session_id' => $this->session->id, 'status' => AssistantTurnStatus::AwaitingApproval]);
    AssistantAction::factory()->create(['assistant_turn_id' => $turn->id, 'tool_call_id' => 'call_1']);

    $this->getJson($this->sessionUrl)
        ->assertSuccessful()
        ->assertJsonPath('data.active_turn.status', 'awaiting_approval')
        ->assertJsonPath('data.active_turn.actions.0.tool_call_id', 'call_1')
        ->assertJsonPath('data.active_turn.actions.0.status', 'pending');
});

it('records decisions and rejects ids that are not waiting', function () {
    $turn = AssistantTurn::factory()->create(['assistant_session_id' => $this->session->id, 'status' => AssistantTurnStatus::AwaitingApproval]);
    AssistantAction::factory()->create(['assistant_turn_id' => $turn->id, 'tool_call_id' => 'call_1']);
    AssistantAction::factory()->create(['assistant_turn_id' => $turn->id, 'tool_call_id' => 'call_2']);
    $url = "{$this->sessionUrl}/turns/{$turn->id}/decisions";

    $this->postJson($url, ['decisions' => [['tool_call_id' => 'call_9', 'approve' => true]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('decisions');

    $this->postJson($url, ['decisions' => [['tool_call_id' => 'call_1', 'approve' => false, 'note' => 'No']]])
        ->assertSuccessful()
        ->assertJsonPath('data.turn.status', 'awaiting_approval');

    expect(AssistantAction::query()->where('tool_call_id', 'call_1')->value('status'))->toBe(AssistantActionStatus::Rejected);
});

it('refuses decisions for a turn that is not paused', function () {
    $turn = AssistantTurn::factory()->running()->create(['assistant_session_id' => $this->session->id]);

    $this->postJson("{$this->sessionUrl}/turns/{$turn->id}/decisions", ['decisions' => [['tool_call_id' => 'x', 'approve' => true]]])
        ->assertConflict();
});

it('cancels a turn', function () {
    $turn = AssistantTurn::factory()->create(['assistant_session_id' => $this->session->id]);

    $this->postJson("{$this->sessionUrl}/turns/{$turn->id}/cancel")
        ->assertSuccessful()
        ->assertJsonPath('data.turn.status', 'cancelled');
});

it('hides another member conversation and turns', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    $turn = AssistantTurn::factory()->create(['assistant_session_id' => $this->session->id]);

    Passport::actingAs($member);

    $this->postJson("{$this->sessionUrl}/messages", ['content' => 'Hi'])->assertNotFound();
    $this->getJson("{$this->sessionUrl}/turns/{$turn->id}")->assertNotFound();
    $this->postJson("{$this->sessionUrl}/turns/{$turn->id}/cancel")->assertNotFound();
});

it('only lets the owner subscribe to a conversation channel', function () {
    $gate = app(WorkspaceChannelGate::class);
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);

    expect($gate->assistantSession($this->owner, $this->workspace->id, $this->session->id))->toBeTrue()
        ->and($gate->assistantSession($member, $this->workspace->id, $this->session->id))->toBeFalse();
});

it('lists tools with their rules and updates them', function () {
    $this->getJson("{$this->base}/tool-rules")
        ->assertSuccessful()
        ->assertJsonPath('data.tools.0.name', 'remember')
        ->assertJsonPath('data.tools.0.default_rule', 'allow');

    $this->putJson("{$this->base}/tool-rules", ['rules' => [['tool' => 'remember', 'rule' => 'ask']]])
        ->assertSuccessful()
        ->assertJsonPath('data.tools.0.rule', 'ask');

    expect($this->assistant->toolRules()->value('rule'))->toBe(AssistantToolRule::Ask);

    $this->putJson("{$this->base}/tool-rules", ['rules' => [['tool' => 'remember', 'rule' => null]]])->assertSuccessful();

    expect($this->assistant->toolRules()->count())->toBe(0);
});

it('rejects a rule for an unknown tool', function () {
    $this->putJson("{$this->base}/tool-rules", ['rules' => [['tool' => 'launch_rockets', 'rule' => 'allow']]])
        ->assertUnprocessable();
});

it('lists and deletes memories', function () {
    $memory = $this->assistant->memories()->create(['key' => 'team', 'value' => 'Growth']);

    $this->getJson("{$this->base}/memories")
        ->assertSuccessful()
        ->assertJsonPath('data.memories.0.key', 'team');

    $this->deleteJson("{$this->base}/memories/{$memory->id}")->assertNoContent();

    expect($this->assistant->memories()->count())->toBe(0);
});

it('lists follow-ups with the waiting ones first and cancels a waiting one', function () {
    $settled = AssistantFollowUp::factory()->create([
        'assistant_id' => $this->assistant->id,
        'status' => AssistantFollowUpStatus::Replied,
        'resolved_at' => now(),
    ]);
    $later = AssistantFollowUp::factory()->create(['assistant_id' => $this->assistant->id, 'remind_at' => now()->addDays(5)]);
    $sooner = AssistantFollowUp::factory()->create(['assistant_id' => $this->assistant->id, 'remind_at' => now()->addDay()]);

    $this->getJson("{$this->base}/follow-ups")
        ->assertSuccessful()
        ->assertJsonPath('data.follow_ups.0.id', $sooner->id)
        ->assertJsonPath('data.follow_ups.1.id', $later->id)
        ->assertJsonPath('data.follow_ups.2.id', $settled->id)
        ->assertJsonPath('data.follow_ups.2.status', 'replied');

    $this->deleteJson("{$this->base}/follow-ups/{$sooner->id}")->assertNoContent();

    expect($sooner->refresh()->status)->toBe(AssistantFollowUpStatus::Cancelled);
});

it('does not show or cancel another assistant\'s follow-ups', function () {
    $theirs = AssistantFollowUp::factory()->create();

    $this->getJson("{$this->base}/follow-ups")->assertJsonCount(0, 'data.follow_ups');
    $this->deleteJson("{$this->base}/follow-ups/{$theirs->id}")->assertNotFound();

    expect($theirs->refresh()->isPending())->toBeTrue();
});
