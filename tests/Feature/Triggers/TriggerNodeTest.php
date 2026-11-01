<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Enums\Triggers\TriggerType;
use App\Exceptions\WorkflowValidationException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerService;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Collection;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User, 2: Workflow}
 */
function triggerNodeWorkflow(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    return [$workspace, $owner, $workflow];
}

/**
 * Two triggers feeding one shared step, each with a step of its own.
 *
 * @param  array<string, mixed>  $webhookConfig
 */
function publishTwoTriggerGraph(Workflow $workflow, User $owner, array $webhookConfig = [], string $cron = '0 9 * * *'): void
{
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'hook', 'type' => 'webhook_trigger', 'config' => $webhookConfig],
            ['key' => 'clock', 'type' => 'schedule_trigger', 'config' => ['cron' => $cron, 'timezone' => 'Asia/Kolkata']],
            ['key' => 'hook_only', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'clock_only', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'shared', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [
            ['from' => 'hook', 'to' => 'hook_only'],
            ['from' => 'clock', 'to' => 'clock_only'],
            ['from' => 'hook', 'to' => 'shared'],
            ['from' => 'clock', 'to' => 'shared'],
        ],
    ]);

    $workflow->publishVersion(publisher: $owner);
}

function nodeTriggersOf(Workflow $workflow): Collection
{
    return Trigger::query()->where('target_id', $workflow->id)->get()->keyBy('trigger_node_key');
}

it('lists the four trigger nodes in the Triggers & Events catalog', function () {
    $nodes = collect(app(NodeRegistry::class)->catalog())->where('category', 'triggers-events');

    expect($nodes->pluck('type')->sort()->values()->all())
        ->toBe(['manual_trigger', 'polling_trigger', 'schedule_trigger', 'webhook_trigger'])
        ->and($nodes->pluck('node_kind')->unique()->values()->all())->toBe(['trigger']);
});

it('keeps the manual trigger runnable but stops offering it', function () {
    $registry = app(NodeRegistry::class);
    $catalog = collect($registry->catalog())->keyBy('type');

    expect($catalog['manual_trigger']['listed'])->toBeFalse()
        ->and($catalog['webhook_trigger']['listed'])->toBeTrue()
        ->and($registry->has('manual_trigger'))->toBeTrue()
        ->and(collect($registry->placeableTypes())->pluck('type'))->not->toContain('manual_trigger')
        ->and(collect($registry->placeableTypes())->pluck('type'))->toContain('webhook_trigger');
});

it('creates a trigger row per schedule and webhook node on publish', function () {
    [, $owner, $workflow] = triggerNodeWorkflow();

    publishTwoTriggerGraph($workflow, $owner);

    $triggers = nodeTriggersOf($workflow);

    expect($triggers)->toHaveCount(2)
        ->and($triggers['hook']->type)->toBe(TriggerType::Webhook)
        ->and($triggers['hook']->token)->not->toBeNull()
        ->and($triggers['clock']->type)->toBe(TriggerType::Schedule)
        ->and($triggers['clock']->token)->toBeNull()
        ->and($triggers['clock']->config)->toBe(['cron' => '0 9 * * *', 'timezone' => 'Asia/Kolkata'])
        ->and($triggers['clock']->created_by)->toBe($owner->id);
});

it('does not touch live triggers on a draft save', function () {
    [, $owner, $workflow] = triggerNodeWorkflow();

    $workflow->replaceGraph(['nodes' => [['key' => 'hook', 'type' => 'webhook_trigger', 'config' => []]], 'edges' => []]);

    expect(nodeTriggersOf($workflow))->toHaveCount(0);
});

it('keeps the token and paused state on republish, and updates config', function () {
    [, $owner, $workflow] = triggerNodeWorkflow();
    publishTwoTriggerGraph($workflow, $owner);
    $before = nodeTriggersOf($workflow);
    $before['clock']->forceFill(['is_active' => false])->save();

    publishTwoTriggerGraph($workflow->fresh(), $owner, cron: '30 18 * * 1-5');

    $after = nodeTriggersOf($workflow);

    expect($after['hook']->id)->toBe($before['hook']->id)
        ->and($after['hook']->token)->toBe($before['hook']->token)
        ->and($after['clock']->is_active)->toBeFalse()
        ->and($after['clock']->config['cron'])->toBe('30 18 * * 1-5');
});

it('deletes the row when its node is removed and republished', function () {
    [, $owner, $workflow] = triggerNodeWorkflow();
    publishTwoTriggerGraph($workflow, $owner);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'hook', 'type' => 'webhook_trigger', 'config' => []],
            ['key' => 'hook_only', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'hook', 'to' => 'hook_only']],
    ]);
    $workflow->publishVersion(publisher: $owner);

    expect(nodeTriggersOf($workflow)->keys()->all())->toBe(['hook']);
});

it('leaves triggers made through the API alone', function () {
    [, $owner, $workflow] = triggerNodeWorkflow();
    $standalone = Trigger::factory()->webhook()->forWorkflow($workflow)->create();

    publishTwoTriggerGraph($workflow, $owner);

    expect(Trigger::find($standalone->id))->not->toBeNull();
});

it('carries the webhook node\'s authentication to the row, never a provider preset', function () {
    [, $owner, $workflow] = triggerNodeWorkflow();

    publishTwoTriggerGraph($workflow, $owner, ['authentication' => 'header_token', 'header_name' => 'X-Acme-Token']);

    $hook = nodeTriggersOf($workflow)['hook'];

    expect($hook->config)->toMatchArray(['authentication' => 'header_token', 'header_name' => 'X-Acme-Token'])
        ->and($hook->preset_id)->toBeNull();
});

it('refuses to publish a polling node pointed at another member\'s personal account', function () {
    [$workspace, $owner, $workflow] = triggerNodeWorkflow();
    $someoneElse = User::factory()->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->create(['scope' => 'personal', 'created_by' => $someoneElse->id]);

    $workflow->replaceGraph([
        'nodes' => [['key' => 'poll', 'type' => 'polling_trigger', 'config' => ['url' => 'https://feed.example.com/items', 'credential_id' => $credential->id]]],
        'edges' => [],
    ]);

    expect(fn () => $workflow->publishVersion(publisher: $owner))->toThrow(WorkflowValidationException::class);
    expect(nodeTriggersOf($workflow))->toHaveCount(0)
        ->and($workflow->fresh()->current_version_id)->toBeNull();
});

it('refuses to publish an invalid cron or timezone', function (array $config) {
    [, $owner, $workflow] = triggerNodeWorkflow();

    $workflow->replaceGraph(['nodes' => [['key' => 'clock', 'type' => 'schedule_trigger', 'config' => $config]], 'edges' => []]);

    expect(fn () => $workflow->publishVersion(publisher: $owner))->toThrow(WorkflowValidationException::class);
})->with([
    'bad cron' => [['cron' => 'every day']],
    'bad timezone' => [['cron' => '0 9 * * *', 'timezone' => 'Mars/Olympus']],
]);

it('refuses a trigger condition it could never evaluate', function (array $filters) {
    [, $owner, $workflow] = triggerNodeWorkflow();

    $workflow->replaceGraph(['nodes' => [['key' => 'hook', 'type' => 'webhook_trigger', 'config' => ['filters' => $filters]]], 'edges' => []]);

    expect(fn () => $workflow->publishVersion(publisher: $owner))->toThrow(WorkflowValidationException::class);
})->with([
    'unknown operator' => [[['path' => 'action', 'operator' => 'sounds_like', 'value' => 'x']]],
    'no path' => [[['operator' => 'equals', 'value' => 'x']]],
    'broken pattern' => [[['path' => 'action', 'operator' => 'matches_regex', 'value' => '(']]],
    'unknown source' => [[['path' => 'action', 'source' => 'cookie', 'value' => 'x']]],
]);

it('refuses a connection leading into a trigger', function () {
    [, , $workflow] = triggerNodeWorkflow();

    expect(fn () => $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'first', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'hook', 'type' => 'webhook_trigger', 'config' => []],
        ],
        'edges' => [['from' => 'first', 'to' => 'hook']],
    ]))->toThrow(WorkflowValidationException::class);
});

it('runs only the firing trigger\'s branch, plus steps it shares', function () {
    [, $owner, $workflow] = triggerNodeWorkflow();
    publishTwoTriggerGraph($workflow, $owner);
    $trigger = nodeTriggersOf($workflow)['hook'];
    $trigger->forceFill(['created_by' => $owner->id])->save();

    $event = app(TriggerService::class)->receive($trigger, TriggerType::Webhook, ['action' => 'opened'], 'delivery-1');

    $run = Run::findOrFail($event->fresh()->run_id)->load('nodeRuns');
    $statuses = $run->nodeRuns->pluck('status', 'key');

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->entry_node_key)->toBe('hook')
        ->and($statuses['hook'])->toBe(NodeRunStatus::Completed)
        ->and($statuses['hook_only'])->toBe(NodeRunStatus::Completed)
        ->and($statuses['shared'])->toBe(NodeRunStatus::Completed)
        ->and($statuses['clock'])->toBe(NodeRunStatus::Skipped)
        ->and($statuses['clock_only'])->toBe(NodeRunStatus::Skipped)
        ->and($run->nodeRuns->firstWhere('key', 'hook')->output['payload'])->toBe(['action' => 'opened']);
});

it('still starts every entry node for a run with no entry node', function () {
    [, $owner, $workflow] = triggerNodeWorkflow();
    publishTwoTriggerGraph($workflow, $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh(), ['x' => 1], $owner)->load('nodeRuns');

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->nodeRuns->where('status', NodeRunStatus::Completed)->count())->toBe(5);
});

it('refuses to change a node trigger\'s config or delete it through the API, but allows pausing', function () {
    [$workspace, $owner, $workflow] = triggerNodeWorkflow();
    publishTwoTriggerGraph($workflow, $owner);
    $trigger = nodeTriggersOf($workflow)['hook'];
    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}", ['config' => ['filters' => []]])
        ->assertUnprocessable();

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}")
        ->assertUnprocessable();

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}", ['is_active' => false, 'signing_secret' => 's3cret'])
        ->assertSuccessful()
        ->assertJsonPath('data.trigger.is_active', false)
        ->assertJsonPath('data.trigger.has_signing_secret', true)
        ->assertJsonPath('data.trigger.trigger_node_key', 'hook');
});

it('filters the trigger list by target', function () {
    [$workspace, $owner, $workflow] = triggerNodeWorkflow();
    $other = Workflow::factory()->forWorkspace($workspace)->create();
    Trigger::factory()->webhook()->forWorkflow($workflow)->create();
    Trigger::factory()->webhook()->forWorkflow($other)->create();
    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers?target_type=workflow&target_id={$workflow->id}")
        ->assertSuccessful();

    expect($response->json('data.triggers'))->toHaveCount(1)
        ->and($response->json('data.triggers.0.target_id'))->toBe($workflow->id);
});
