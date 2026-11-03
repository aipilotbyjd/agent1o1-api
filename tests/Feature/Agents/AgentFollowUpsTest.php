<?php

use App\Actions\Agents\ResolveAgentActionsAction;
use App\Ai\Agents\Workspace\WorkspaceAgent;
use App\Ai\Tools\FollowUps\CancelFollowUpTool;
use App\Ai\Tools\FollowUps\ListFollowUpsTool;
use App\Ai\Tools\FollowUps\ScheduleFollowUpTool;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\Agents\AutonomyMode;
use App\Enums\Triggers\TriggerType;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Services\Agents\AgentFollowUps;
use App\Services\Agents\AgentRunner;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-05-04 08:00:00', 'UTC'));

    $this->owner = User::factory()->create(['timezone' => 'Europe/Lisbon']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['autonomy_mode' => AutonomyMode::Autopilot, 'allow_self_clone' => false]);
    $this->session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->followUps = app(AgentFollowUps::class);
});

it('schedules a follow-up that continues this conversation, on the person\'s own clock', function () {
    WorkspaceAgent::fake([
        new ToolCall('call-1', ScheduleFollowUpTool::NAME, ['task' => 'Check whether PR #42 was merged.', 'title' => 'PR #42', 'run_at' => '2026-05-05T09:00']),
        'I will check tomorrow at 9:00.',
    ]);

    app(AgentRunner::class)->run($this->session, 'Check this PR again tomorrow at 9.');

    $trigger = Trigger::query()->sole();

    expect($trigger->type)->toBe(TriggerType::Schedule)
        ->and($trigger->target_id)->toBe($this->agent->id)
        ->and($trigger->created_by)->toBe($this->owner->id)
        ->and($trigger->is_active)->toBeTrue()
        ->and($trigger->config['timezone'])->toBe('Europe/Lisbon')
        ->and($trigger->config['follow_up'])->toMatchArray(['title' => 'PR #42', 'task' => 'Check whether PR #42 was merged.', 'agent_session_id' => $this->session->id])
        // 09:00 in Lisbon (UTC+1 in May) is 08:00 UTC.
        ->and($trigger->next_run_at->toIso8601String())->toBe('2026-05-05T08:00:00+00:00');

    $result = json_decode(collect($this->session->messages()->where('role', AgentMessageRole::Assistant)->sole()->tool_results)->firstWhere('id', 'call-1')['result'], true);
    expect($result)->toMatchArray(['scheduled' => true, 'id' => $trigger->id, 'repeats' => false, 'timezone' => 'Europe/Lisbon']);
});

it('waits for approval before scheduling in Ask mode', function () {
    $this->agent->update(['autonomy_mode' => AutonomyMode::Ask]);

    WorkspaceAgent::fake([
        new ToolCall('call-1', ScheduleFollowUpTool::NAME, ['task' => 'Summarise the tickets.', 'cron' => '0 9 * * 1']),
        'Scheduled for Mondays.',
    ]);

    app(AgentRunner::class)->run($this->session, 'Every Monday, summarise last week\'s tickets.');

    expect(Trigger::query()->count())->toBe(0);
    expect(AgentAction::query()->sole()->status)->toBe(AgentActionStatus::Pending);

    app(ResolveAgentActionsAction::class)->execute($this->owner, [['action_id' => AgentAction::query()->sole()->id, 'decision' => 'approve']]);

    expect(Trigger::query()->sole()->config['cron'])->toBe('0 9 * * 1');
});

it('refuses a follow-up it cannot schedule as asked', function (array $input, string $message) {
    expect(fn () => $this->followUps->schedule($this->agent, $this->session, $this->owner->id, $input))
        ->toThrow(ValidationException::class, $message);

    expect(Trigger::query()->count())->toBe(0);
})->with([
    'no task' => [['run_at' => '2026-05-05T09:00'], 'Say what you will do'],
    'no time' => [['task' => 'Check it.'], 'either run_at'],
    'both kinds of time' => [['task' => 'Check it.', 'run_at' => '2026-05-05T09:00', 'cron' => '0 9 * * *'], 'either run_at'],
    'in the past' => [['task' => 'Check it.', 'run_at' => '2026-05-01T09:00'], 'in the past'],
    'too far ahead' => [['task' => 'Check it.', 'run_at' => '2028-05-01T09:00'], 'too far ahead'],
    'not a date' => [['task' => 'Check it.', 'run_at' => 'whenever'], 'not a date'],
    'an invalid cron' => [['task' => 'Check it.', 'cron' => 'every day'], 'not a valid'],
    'too often' => [['task' => 'Check it.', 'cron' => '*/5 * * * *'], 'at most once every 60 minutes'],
]);

it('caps how many follow-ups an agent can have waiting', function () {
    config(['triggers.agent_follow_ups.max_active_per_agent' => 1]);

    $this->followUps->schedule($this->agent, $this->session, $this->owner->id, ['task' => 'One.', 'run_at' => '2026-05-05T09:00']);

    expect(fn () => $this->followUps->schedule($this->agent, $this->session, $this->owner->id, ['task' => 'Two.', 'run_at' => '2026-05-06T09:00']))
        ->toThrow(ValidationException::class, 'most follow-ups');
});

it('reports a validation problem to the agent instead of failing the turn', function () {
    $tool = new ScheduleFollowUpTool($this->agent, $this->session, $this->followUps, $this->owner->id);

    $result = json_decode((string) $tool->handle(new Request(['task' => 'Check it.', 'run_at' => '2026-05-01T09:00'], 'call-1')), true);

    expect($result['error'])->toContain('in the past');
    expect(Trigger::query()->count())->toBe(0);
});

it('continues the same conversation when the follow-up fires, then switches a one-time one off', function () {
    $trigger = $this->followUps->schedule($this->agent, $this->session, $this->owner->id, ['task' => 'Check whether PR #42 was merged.', 'title' => 'PR #42', 'run_at' => '2026-05-05T09:00']);

    WorkspaceAgent::fake(['PR #42 was merged this morning.']);

    $this->travelTo(Carbon::parse('2026-05-05 08:00:30', 'UTC'));
    $this->artisan('triggers:run-due')->assertSuccessful();

    $messages = $this->session->messages()->orderBy('id')->get();
    $followUp = $messages->firstWhere('role', AgentMessageRole::User);

    expect(AgentSession::query()->count())->toBe(1);
    expect($followUp->content)->toContain('[Scheduled follow-up]')->toContain('Check whether PR #42 was merged.');
    expect($followUp->origin)->toMatchArray(['type' => 'follow_up', 'trigger_id' => $trigger->id, 'title' => 'PR #42']);
    expect($messages->last()->content)->toBe('PR #42 was merged this morning.');

    $run = Run::query()->where('runnable_id', $this->session->id)->sole();
    expect($run->trigger_type)->toBe('schedule');
    expect($trigger->fresh()->is_active)->toBeFalse();
});

it('runs in a fresh conversation when the original one is gone', function () {
    $this->followUps->schedule($this->agent, $this->session, $this->owner->id, ['task' => 'Check it.', 'title' => 'Check', 'run_at' => '2026-05-05T09:00']);
    $this->session->delete();

    WorkspaceAgent::fake(['Checked.']);

    $this->travelTo(Carbon::parse('2026-05-05 08:00:30', 'UTC'));
    $this->artisan('triggers:run-due')->assertSuccessful();

    $session = AgentSession::query()->sole();
    expect($session->title)->toBe('Check');
    expect($session->messages()->where('role', AgentMessageRole::Assistant)->sole()->content)->toBe('Checked.');
});

it('lists and cancels only the follow-ups set for the same person', function () {
    $mine = $this->followUps->schedule($this->agent, $this->session, $this->owner->id, ['task' => 'Mine.', 'run_at' => '2026-05-05T09:00']);

    $colleague = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $colleague->id, 'role' => Role::Member, 'joined_at' => now()]);
    $theirSession = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $colleague->id]);
    $theirs = $this->followUps->schedule($this->agent, $theirSession, $colleague->id, ['task' => 'Theirs.', 'run_at' => '2026-05-05T10:00']);

    $listed = json_decode((string) (new ListFollowUpsTool($this->agent, $this->followUps, $this->owner->id))->handle(new Request([])), true);
    expect(array_column($listed, 'id'))->toBe([$mine->id]);

    $cancel = new CancelFollowUpTool($this->agent, $this->followUps, $this->owner->id);

    expect((string) $cancel->handle(new Request(['id' => $theirs->id])))->toContain('No follow-up of yours');
    expect($theirs->fresh()->is_active)->toBeTrue();

    expect((string) $cancel->handle(new Request(['id' => $mine->id])))->toContain('Cancelled');
    expect($mine->fresh()->is_active)->toBeFalse();
});

it('keeps a follow-up a follow-up when its trigger is edited through the API', function () {
    $trigger = $this->followUps->schedule($this->agent, $this->session, $this->owner->id, ['task' => 'Check it.', 'cron' => '0 9 * * 1']);

    Passport::actingAs($this->owner);

    $this->patchJson("/api/v1/workspaces/{$this->workspace->id}/triggers/{$trigger->id}", [
        'config' => ['cron' => '0 10 * * 1', 'timezone' => 'Europe/Lisbon'],
    ])->assertOk();

    expect($trigger->fresh()->config)
        ->toMatchArray(['cron' => '0 10 * * 1'])
        ->and($trigger->fresh()->config['follow_up']['task'])->toBe('Check it.');
});

it('never continues a conversation that belongs to someone other than whoever set the follow-up', function () {
    $colleague = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $colleague->id, 'role' => Role::Member, 'joined_at' => now()]);

    // A follow-up pointed at the owner's conversation, but set by a colleague.
    $trigger = $this->followUps->schedule($this->agent, $this->session, $colleague->id, ['task' => 'Post into their chat.', 'run_at' => '2026-05-05T09:00', 'timezone' => 'Europe/Lisbon']);

    WorkspaceAgent::fake(['Done.']);

    $this->travelTo(Carbon::parse('2026-05-05 08:00:30', 'UTC'));
    $this->artisan('triggers:run-due')->assertSuccessful();

    expect($this->session->messages()->count())->toBe(0);
    expect(AgentSession::query()->where('user_id', $colleague->id)->sole()->messages()->count())->toBe(2);
    expect($trigger->fresh()->is_active)->toBeFalse();
});
