<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Services\Triggers\TriggerService;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Queue;

function makeTrigger(array $overrides = []): Trigger
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return Trigger::factory()->webhook()->forWorkspace($workspace)->create($overrides);
}

it('creates a trigger and issues a token only for webhook types', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $webhook = app(TriggerService::class)->create(
        $workspace,
        TriggerTargetType::Workflow,
        1,
        TriggerType::Webhook,
        [],
        $owner,
    );

    $manual = app(TriggerService::class)->create(
        $workspace,
        TriggerTargetType::Workflow,
        1,
        TriggerType::Manual,
        [],
        $owner,
    );

    expect($webhook->token)->not->toBeNull();
    expect($manual->token)->toBeNull();
});

it('dedupes concurrent deliveries of the same delivery id to a single row', function () {
    $trigger = makeTrigger();
    $service = app(TriggerService::class);

    $first = $service->receive($trigger, TriggerType::Webhook, ['a' => 1], 'delivery-1');
    $second = $service->receive($trigger, TriggerType::Webhook, ['a' => 1], 'delivery-1');

    expect($first->id)->toBe($second->id);
    expect($trigger->events()->count())->toBe(1);
    expect($second->fresh()->duplicate_count)->toBe(1);
});

it('exercises the unique-constraint catch, not just the read-check, on a concurrent duplicate', function () {
    $trigger = makeTrigger();
    $service = app(TriggerService::class);

    // Simulate a row that appeared between the read-check and the insert by
    // pre-creating it, then asserting receive() still resolves to one row.
    $trigger->events()->create([
        'source' => TriggerType::Webhook,
        'status' => TriggerEventStatus::Queued,
        'payload' => ['a' => 1],
        'delivery_id' => 'delivery-race',
    ]);

    expect(fn () => $service->receive($trigger, TriggerType::Webhook, ['a' => 1], 'delivery-race'))
        ->not->toThrow(UniqueConstraintViolationException::class);

    expect($trigger->events()->count())->toBe(1);
});

it('ignores an event that fails a configured filter, without dispatching a job', function () {
    Queue::fake();

    $trigger = makeTrigger([
        'config' => ['filters' => [['path' => 'action', 'operator' => 'equals', 'value' => 'opened']]],
    ]);

    $event = app(TriggerService::class)->receive($trigger, TriggerType::Webhook, ['action' => 'closed'], 'd-1');

    expect($event->status)->toBe(TriggerEventStatus::Ignored);
    Queue::assertNothingPushed();
});

it('holds back an event when a filter uses an operator it does not know', function () {
    Queue::fake();

    $trigger = makeTrigger([
        'config' => ['filters' => [['path' => 'action', 'operator' => 'sounds_like', 'value' => 'opened']]],
    ]);

    $event = app(TriggerService::class)->receive($trigger, TriggerType::Webhook, ['action' => 'opened'], 'd-1');

    expect($event->status)->toBe(TriggerEventStatus::Ignored);
    Queue::assertNothingPushed();
});

it('lets an event through when it matches a pattern or list filter', function () {
    Queue::fake();

    $trigger = makeTrigger([
        'config' => ['filters' => [
            ['path' => 'ref', 'operator' => 'matches_regex', 'value' => '^refs/heads/(main|release-.+)$'],
            ['path' => 'action', 'operator' => 'in_list', 'value' => 'opened, reopened'],
        ]],
    ]);

    $event = app(TriggerService::class)->receive($trigger, TriggerType::Webhook, ['ref' => 'refs/heads/release-2', 'action' => 'reopened'], 'd-2');

    expect($event->status)->not->toBe(TriggerEventStatus::Ignored);
});

it('skips an inactive trigger without creating a queued event', function () {
    $trigger = makeTrigger(['is_active' => false]);

    $event = app(TriggerService::class)->receive($trigger, TriggerType::Webhook, [], 'd-1');

    expect($event->status)->toBe(TriggerEventStatus::Skipped);
});

it('stores a rejected delivery without a delivery id', function () {
    $trigger = makeTrigger();

    $event = app(TriggerService::class)->reject(
        $trigger,
        TriggerType::Webhook,
        ['a' => 1],
        'raw-body',
        [],
        'Signature verification failed.',
    );

    expect($event->status)->toBe(TriggerEventStatus::Rejected);
    expect($event->delivery_id)->toBeNull();
});
