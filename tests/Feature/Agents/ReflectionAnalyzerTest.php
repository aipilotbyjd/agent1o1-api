<?php

use App\Ai\Agents\ReflectionReviewerAgent;
use App\Ai\Tools\SubmitReflectionsTool;
use App\Enums\Agents\ReflectionRunStatus;
use App\Enums\Agents\ReflectionStatus;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Notifications\NotificationEvent;
use App\Enums\RunStatus;
use App\Jobs\Agents\ApplyReflectionJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\ReflectionSettings;
use App\Models\Billing\CreditTransaction;
use App\Models\Runs\Run;
use App\Models\User;
use App\Notifications\Agents\ReflectionReportNotification;
use App\Services\Agents\ReflectionAnalyzer;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;

/**
 * @param  array<int, array<string, mixed>>  $reflections
 */
function reviewerSubmits(array $reflections): void
{
    ReflectionReviewerAgent::fake([new ToolCall('call_1', SubmitReflectionsTool::NAME, ['reflections' => $reflections])]);
}

/**
 * @return array{0: Agent, 1: User}
 */
function reflectionAgent(int $sessionCount = 3): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'You are a helpful assistant.']);

    for ($i = 0; $i < $sessionCount; $i++) {
        $session = AgentSession::factory()->forAgent($agent)->create();
        AgentMessage::factory()->forSession($session)->create(['content' => 'How do I get a refund?']);
        AgentMessage::factory()->forSession($session)->assistant()->create(['content' => 'I am not sure, let me check.']);
    }

    return [$agent->fresh(), $owner];
}

it('skips the run when there are fewer sessions than the minimum threshold', function () {
    [$agent] = reflectionAgent(sessionCount: 2);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 5]);

    $run = app(ReflectionAnalyzer::class)->run($agent);

    expect($run->status)->toBe(ReflectionRunStatus::Skipped);
    expect($run->sessions_analyzed_count)->toBe(2);
    expect($run->reflections)->toHaveCount(0);
});

it('keeps skipped sessions counting toward the next run', function () {
    [$agent] = reflectionAgent(sessionCount: 2);
    $settings = ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 3]);

    app(ReflectionAnalyzer::class)->run($agent);

    expect($settings->fresh()->last_run_at)->toBeNull();

    $session = AgentSession::factory()->forAgent($agent)->create();
    AgentMessage::factory()->forSession($session)->create(['content' => 'How do I get a refund?']);

    reviewerSubmits([]);

    $run = app(ReflectionAnalyzer::class)->run($agent->fresh());

    expect($run->status)->toBe(ReflectionRunStatus::Completed);
    expect($run->sessions_analyzed_count)->toBe(3);
});

it('proposes reflections from the submitted findings', function () {
    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    reviewerSubmits([
        [
            'type' => 'instruction_update',
            'title' => 'Clarify refund policy',
            'rationale' => 'The agent hedges on refund questions across sessions 1-3.',
            'confidence' => 80,
            'session_numbers' => [1, 2, 3],
            'proposed_prompt' => 'Refunds are available within 30 days of purchase.',
            'target_skill_id' => null,
        ],
    ]);

    $run = app(ReflectionAnalyzer::class)->run($agent);

    expect($run->status)->toBe(ReflectionRunStatus::Completed);
    expect($run->sessions_analyzed_count)->toBe(3);

    $reflection = $run->reflections()->sole();
    expect($reflection->title)->toBe('Clarify refund policy');
    expect($reflection->status)->toBe(ReflectionStatus::Pending);
    expect($reflection->confidence)->toBe(80);
    expect($reflection->support_count)->toBe(3);
    expect($reflection->evidence['session_ids'])->toHaveCount(3);

    $analysisRun = Run::where('runnable_type', 'reflection_run')->where('runnable_id', $run->id)->sole();
    expect($analysisRun->trigger_type)->toBe('reflection');
});

it('ignores candidates missing required fields', function () {
    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    reviewerSubmits([
        ['type' => 'bogus_type', 'title' => 'x', 'proposed_prompt' => 'y'],
        ['type' => 'new_skill', 'title' => '', 'proposed_prompt' => 'y'],
    ]);

    $run = app(ReflectionAnalyzer::class)->run($agent);

    expect($run->reflections)->toHaveCount(0);
});

it('fails the run when the model does not submit its findings and keeps the sessions for the next run', function () {
    [$agent] = reflectionAgent(sessionCount: 3);
    $settings = ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    ReflectionReviewerAgent::fake(['I found a few patterns worth fixing.']);

    $run = app(ReflectionAnalyzer::class)->run($agent);

    expect($run->status)->toBe(ReflectionRunStatus::Failed);
    expect($run->skip_reason)->toContain('did not call submit_reflections');
    expect($settings->fresh()->last_run_at)->toBeNull();
});

it('moves the review window only after a completed run', function () {
    [$agent] = reflectionAgent(sessionCount: 2);
    $settings = ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    reviewerSubmits([]);

    $run = app(ReflectionAnalyzer::class)->run($agent);

    expect($run->status)->toBe(ReflectionRunStatus::Completed);
    expect($settings->fresh()->last_run_at?->equalTo($run->started_at))->toBeTrue();

    $this->travel(1)->minutes();

    expect(app(ReflectionAnalyzer::class)->run($agent->fresh())->skip_reason)->toBe('No new chats since the last review.');
});

it('drops a candidate backed by fewer than two cited sessions', function () {
    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    reviewerSubmits([
        ['type' => 'instruction_update', 'title' => 'One-off', 'rationale' => 'r', 'confidence' => 90, 'session_numbers' => [2], 'proposed_prompt' => 'p'],
        ['type' => 'instruction_update', 'title' => 'Made up', 'rationale' => 'r', 'confidence' => 90, 'session_numbers' => [7, 8], 'proposed_prompt' => 'p'],
    ]);

    $run = app(ReflectionAnalyzer::class)->run($agent);

    expect($run->status)->toBe(ReflectionRunStatus::Completed);
    expect($run->reflections)->toHaveCount(0);
});

it('drops a skill fix whose target is not one of the agent\'s skills', function () {
    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    reviewerSubmits([
        ['type' => 'skill_fix', 'title' => 'Fix it', 'rationale' => 'r', 'confidence' => 90, 'session_numbers' => [1, 2], 'proposed_prompt' => 'p', 'target_skill_id' => 'not-a-skill'],
    ]);

    expect(app(ReflectionAnalyzer::class)->run($agent)->reflections)->toHaveCount(0);
});

it('does not re-propose a dismissed reflection', function () {
    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    reviewerSubmits([
        ['type' => 'instruction_update', 'title' => 'Clarify refund policy', 'rationale' => 'r', 'confidence' => 90, 'session_numbers' => [1, 2], 'proposed_prompt' => 'p'],
    ]);
    $first = app(ReflectionAnalyzer::class)->run($agent)->reflections()->sole();
    $first->forceFill(['status' => ReflectionStatus::Dismissed])->save();

    $this->travel(1)->minutes();
    AgentSession::factory()->forAgent($agent)->count(2)->create();

    expect(app(ReflectionAnalyzer::class)->run($agent->fresh())->reflections)->toHaveCount(0);
});

it('shows the reviewer messages in order with tool calls and failed turns', function () {
    [$agent] = reflectionAgent(sessionCount: 0);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 1]);

    $session = AgentSession::factory()->forAgent($agent)->create();
    AgentMessage::factory()->forSession($session)->create(['content' => 'Find the Acme deal', 'created_at' => now()->subMinute()]);
    AgentMessage::factory()->forSession($session)->assistant()->create([
        'content' => 'Found it.',
        'tool_calls' => [['id' => 'call_1', 'name' => 'search_crm', 'arguments' => ['query' => 'Acme']]],
    ]);
    $session->runs()->create(['workspace_id' => $agent->workspace_id, 'trigger_type' => 'manual'])
        ->forceFill(['status' => RunStatus::Failed, 'error' => 'CRM timed out'])->save();

    reviewerSubmits([]);

    app(ReflectionAnalyzer::class)->run($agent->fresh());

    ReflectionReviewerAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains(
        $prompt->prompt,
        'Session 1 ('.now()->toDateString()."):\nuser: Find the Acme deal\ntool call: search_crm({\"query\":\"Acme\"})\nassistant: Found it.\nturn failed: CRM timed out",
    ));
});

it('bills a completed review and records its usage', function () {
    [$agent] = reflectionAgent(sessionCount: 2);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    reviewerSubmits([]);

    $run = app(ReflectionAnalyzer::class)->run($agent);

    $transaction = CreditTransaction::where('source_type', CreditTransactionType::Reflection)->where('source_id', $run->id)->sole();

    expect($run->usage)->not->toBeNull();
    expect($run->runs()->sole()->totalCreditsUsed())->toBe($transaction->credits);
});

it('auto-applies eligible reflections only when auto-apply is enabled', function () {
    Bus::fake();

    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->autoApply()->create(['min_chats_threshold' => 2]);

    reviewerSubmits([
        [
            'type' => 'instruction_update',
            'title' => 'Clarify refund policy',
            'rationale' => 'Recurs across sessions.',
            'confidence' => 90,
            'session_numbers' => [1, 2, 3],
            'proposed_prompt' => 'Refunds are available within 30 days.',
        ],
    ]);

    app(ReflectionAnalyzer::class)->run($agent);

    Bus::assertDispatched(ApplyReflectionJob::class);
});

it('does not auto-apply a low-confidence reflection even with auto-apply enabled', function () {
    Bus::fake();

    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->autoApply()->create(['min_chats_threshold' => 2]);

    reviewerSubmits([
        [
            'type' => 'instruction_update',
            'title' => 'Weak signal',
            'rationale' => 'Only barely recurring.',
            'confidence' => 40,
            'session_numbers' => [1, 2],
            'proposed_prompt' => 'Some change.',
        ],
    ]);

    app(ReflectionAnalyzer::class)->run($agent);

    Bus::assertNotDispatched(ApplyReflectionJob::class);
});

it('does not auto-apply a tool_access reflection regardless of confidence', function () {
    Bus::fake();

    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->autoApply()->create(['min_chats_threshold' => 2]);

    reviewerSubmits([
        [
            'type' => 'tool_access',
            'title' => 'Needs Salesforce access',
            'rationale' => 'The agent keeps asking for data it cannot fetch.',
            'confidence' => 99,
            'session_numbers' => [1, 2, 3],
            'proposed_prompt' => 'Grant the Salesforce connector.',
        ],
    ]);

    app(ReflectionAnalyzer::class)->run($agent);

    Bus::assertNotDispatched(ApplyReflectionJob::class);
});

it('supersedes a still-pending reflection with the same title', function () {
    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 1]);

    reviewerSubmits([
        ['type' => 'instruction_update', 'title' => 'Same pattern', 'rationale' => 'r', 'confidence' => 60, 'session_numbers' => [1, 2], 'proposed_prompt' => 'v1'],
    ]);
    $firstRun = app(ReflectionAnalyzer::class)->run($agent);
    $first = $firstRun->reflections()->sole();

    $this->travel(1)->minutes();
    AgentSession::factory()->forAgent($agent)->count(2)->create();

    reviewerSubmits([
        ['type' => 'instruction_update', 'title' => 'Same pattern', 'rationale' => 'r2', 'confidence' => 70, 'session_numbers' => [1, 2], 'proposed_prompt' => 'v2'],
    ]);
    app(ReflectionAnalyzer::class)->run($agent);

    expect($first->fresh()->status)->toBe(ReflectionStatus::Superseded);
    expect($agent->reflections()->where('status', ReflectionStatus::Pending->value)->sole()->proposed_prompt)->toBe('v2');
});

it('notifies workspace owners and admins when reflections are proposed', function () {
    Notification::fake();

    [$agent, $owner] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    reviewerSubmits([
        ['type' => 'instruction_update', 'title' => 'x', 'rationale' => 'r', 'confidence' => 60, 'session_numbers' => [1, 2], 'proposed_prompt' => 'p'],
    ]);

    app(ReflectionAnalyzer::class)->run($agent);

    Notification::assertSentTo($owner, ReflectionReportNotification::class, fn ($notification) => $notification->event === NotificationEvent::ReflectionRunCompleted);
});

it('only notifies about a skipped run when notify_on_skip is enabled', function () {
    Notification::fake();

    [$agent] = reflectionAgent(sessionCount: 1);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 5]);

    app(ReflectionAnalyzer::class)->run($agent);

    Notification::assertNothingSent();
});

it('leaves conversations run by subagents out of the review', function () {
    [$agent, $owner] = reflectionAgent(sessionCount: 2);
    $parent = $agent->sessions()->oldest()->first();
    foreach (range(1, 3) as $i) {
        AgentSession::factory()->forAgent($agent)->create(['parent_session_id' => $parent->id]);
    }
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 3]);

    $reflectionRun = app(ReflectionAnalyzer::class)->run($agent->fresh());

    expect($reflectionRun->status)->toBe(ReflectionRunStatus::Skipped);
    expect($reflectionRun->sessions_analyzed_count)->toBe(2);
});

it('reviews only the most recent sessions when there are too many', function () {
    [$agent] = reflectionAgent(sessionCount: 52);
    reviewerSubmits([]);

    $reflectionRun = app(ReflectionAnalyzer::class)->run($agent);

    expect($reflectionRun->status)->toBe(ReflectionRunStatus::Completed);
    expect($reflectionRun->sessions_analyzed_count)->toBe(50);
});
