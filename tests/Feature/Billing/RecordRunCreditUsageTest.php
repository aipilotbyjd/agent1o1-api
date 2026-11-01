<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Actions\Workflows\CancelRunAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Agents\AdHocPromptAgent;
use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Listeners\Workflows\RecordRunCreditUsage;
use App\Models\Agents\Agent;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

it('charges one credit_transaction per completed node run', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b']],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);

    $transactions = CreditTransaction::where('workspace_id', $workspace->id)
        ->where('source_type', CreditTransactionType::NodeRun)
        ->get();

    expect($transactions)->toHaveCount(2);
    expect($transactions->pluck('source_id')->sort()->values()->all())
        ->toBe($run->nodeRuns->pluck('id')->sort()->values()->all());
    expect($transactions->every(fn ($t) => $t->credits === 1))->toBeTrue();

    expect($workspace->currentUsagePeriod()->credits_used)->toBe(2);
});

it('adds run_code\'s configured node cost on top of the base credit', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [['op' => 'set', 'output' => 'x', 'value' => 'y']],
            ]],
        ],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    app(StartWorkflowRunAction::class)->execute($workflow);

    $transaction = CreditTransaction::where('source_type', CreditTransactionType::NodeRun)->sole();

    expect($transaction->credits)->toBe(4);
    expect($workspace->currentUsagePeriod()->credits_used)->toBe(4);
});

it('persists a node run\'s usage so it feeds CreditMeter\'s token-based cost', function () {
    AdHocPromptAgent::fake(['a canned reply']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'ask_ai', 'config' => ['prompt' => 'hi']]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $run = $run->fresh(['nodeRuns']);

    $transaction = CreditTransaction::where('source_type', CreditTransactionType::NodeRun)->sole();

    expect($transaction->credits)->toBeGreaterThanOrEqual(1);
    expect($run->nodeRuns->first()->usage)->not->toBeNull();
});

it('charges one credit_transaction per agent turn', function () {
    WorkspaceAgent::fake(['Hello there!']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    $reply = app(SendAgentMessageAction::class)->execute($session, 'Hi!');

    $transaction = CreditTransaction::where('source_type', CreditTransactionType::AgentStep)->sole();

    expect($transaction->source_id)->toBe($reply->id);
    expect($transaction->workspace_id)->toBe($workspace->id);
    expect($workspace->currentUsagePeriod()->credits_used)->toBe($transaction->credits);
    expect($session->runs()->sole()->totalCreditsUsed())->toBe($transaction->credits);
});

it('captures the model, tool call count, and duration CreditMeter needs to price a chat turn', function () {
    WorkspaceAgent::fake(['Hello there!']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    $reply = app(SendAgentMessageAction::class)->execute($session, 'Hi!');

    expect($reply->usage)->toHaveKeys(['provider', 'model', 'tool_call_count', 'duration_seconds']);
    expect($reply->usage['tool_call_count'])->toBe(0);
    expect($reply->usage['duration_seconds'])->toBeGreaterThanOrEqual(0);
});

it('does not double-bill a run whose charge listener is replayed', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b']],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    expect($workspace->currentUsagePeriod()->credits_used)->toBe(2);

    // The listener is queued and bills node runs one at a time, so a retry
    // after a mid-loop failure re-enters it with charges already written.
    app(RecordRunCreditUsage::class)->handle(new RunCompleted($run->fresh()));
    app(RecordRunCreditUsage::class)->handle(new RunCompleted($run->fresh()));

    expect(CreditTransaction::where('workspace_id', $workspace->id)->count())->toBe(2);
    expect($workspace->currentUsagePeriod()->credits_used)->toBe(2);
});

it('bills a run that overruns the remaining balance rather than losing the record', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 1]);
    config(['billing.default_plan' => 'free']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b']],
    ]);
    $workflow->publishVersion(publisher: $owner);

    // One credit left, a two-node run: the gate lets it start, and both nodes
    // are billed even though the second overruns.
    app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    expect(CreditTransaction::where('workspace_id', $workspace->id)->count())->toBe(2);
    expect($workspace->currentUsagePeriod()->credits_used)->toBe(2);
    expect($workspace->fresh()->availableCredits())->toBe(0);
});

it('bills the node runs a failed run got through', function () {
    AdHocPromptAgent::fake(['a canned reply']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'ask', 'type' => 'ask_ai', 'config' => ['prompt' => 'hi']],
            ['key' => 'call', 'type' => 'call_api', 'config' => ['method' => 'GET', 'url' => 'http://127.0.0.1:1/unreachable', 'timeout_seconds' => 1]],
            ['key' => 'never', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'ask', 'to' => 'call'], ['from' => 'call', 'to' => 'never']],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh())->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);

    $charged = CreditTransaction::where('source_type', CreditTransactionType::NodeRun)->pluck('source_id')->all();

    expect($charged)->toEqualCanonicalizing([
        $run->nodeRuns->firstWhere('key', 'ask')->id,
        $run->nodeRuns->firstWhere('key', 'call')->id,
    ]);
    expect($workspace->currentUsagePeriod()->credits_used)->toBe($run->totalCreditsUsed());
});

it('bills the node runs a cancelled run got through, but not the one it was waiting on', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'before', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'gate', 'type' => 'human_approval', 'config' => []],
        ],
        'edges' => [['from' => 'before', 'to' => 'gate']],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    expect(CreditTransaction::count())->toBe(0);

    app(CancelRunAction::class)->execute($run, $owner);

    $transaction = CreditTransaction::where('source_type', CreditTransactionType::NodeRun)->sole();

    expect($transaction->source_id)->toBe($run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'before')->id);
    expect($workspace->currentUsagePeriod()->credits_used)->toBe(1);
});
