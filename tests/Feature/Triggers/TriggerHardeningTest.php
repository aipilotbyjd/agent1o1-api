<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Notifications\Triggers\TriggerDisabledNotification;
use App\Services\Http\SsrfGuard;
use App\Services\Triggers\TriggerService;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function hardeningWorkspace(): array
{
    $owner = User::factory()->create();

    return [app(WorkspaceService::class)->create($owner, ['name' => 'Acme']), $owner];
}

/**
 * @param  array<string, mixed>  $config
 */
function pollingTriggerIn(Workspace $workspace, User $owner, array $config = []): Trigger
{
    $workflow = Workflow::factory()->forWorkspace($workspace)->published()->create();

    return Trigger::factory()->forWorkflow($workflow)->create([
        'type' => TriggerType::Polling,
        'created_by' => $owner->id,
        'config' => ['url' => 'https://feed.example.com/items', 'items_path' => 'items', ...$config],
    ]);
}

describe('polling', function () {
    beforeEach(function () {
        app()->instance(SsrfGuard::class, new SsrfGuard(fn (string $host) => $host === 'feed.example.com' ? ['93.184.216.34'] : []));
        Queue::fake([FireTriggerEvent::class]);
    });

    it('takes a baseline on the first poll, then fires only new items', function () {
        [$workspace, $owner] = hardeningWorkspace();
        $trigger = pollingTriggerIn($workspace, $owner);

        Http::fakeSequence('https://feed.example.com/*')
            ->push(['items' => [['id' => 1], ['id' => 2]]])
            ->push(['items' => [['id' => 1], ['id' => 2], ['id' => 3]]]);

        dispatch_sync(new PollTrigger($trigger));

        expect($trigger->events()->count())->toBe(2)
            ->and($trigger->events()->where('status', TriggerEventStatus::Ignored)->count())->toBe(2);
        Queue::assertNothingPushed();

        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', TriggerEventStatus::Queued)->pluck('delivery_id')->all())->toBe(['3']);
        Queue::assertPushed(FireTriggerEvent::class, 1);
    });

    it('looks at no more than the configured number of items per poll', function () {
        config(['triggers.poll_max_items' => 2]);
        [$workspace, $owner] = hardeningWorkspace();
        $trigger = pollingTriggerIn($workspace, $owner);

        Http::fake(['https://feed.example.com/*' => Http::response(['items' => [['id' => 1], ['id' => 2], ['id' => 3]]])]);

        dispatch_sync(new PollTrigger($trigger));

        // Both are bookkeeping outside #[Fillable]: they must still be saved,
        // or the cursor is lost and the poll interval is never respected.
        expect($trigger->events()->count())->toBe(2)
            ->and($trigger->fresh()->poll_cursor)->toBe(['value' => 2, 'idle' => 0])
            ->and($trigger->fresh()->last_run_at)->not->toBeNull();
    });

    it('sends the connected account\'s token', function () {
        [$workspace, $owner] = hardeningWorkspace();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)->create(['data' => ['api_key' => 'sk_live_poll']]);
        $trigger = pollingTriggerIn($workspace, $owner);
        $trigger->forceFill(['credential_id' => $credential->id])->save();

        Http::fake(['https://feed.example.com/*' => Http::response(['items' => []])]);

        dispatch_sync(new PollTrigger($trigger));

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk_live_poll'));
        expect($trigger->fresh()->consecutive_failure_count)->toBe(0);
    });

    it('records a failure instead of polling with someone else\'s personal account', function () {
        [$workspace, $owner] = hardeningWorkspace();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)->create([
            'scope' => 'personal',
            'created_by' => User::factory()->create()->id,
        ]);
        $trigger = pollingTriggerIn($workspace, $owner);
        $trigger->forceFill(['credential_id' => $credential->id])->save();

        Http::fake();

        dispatch_sync(new PollTrigger($trigger));

        Http::assertNothingSent();
        expect($trigger->fresh()->consecutive_failure_count)->toBe(1);
    });
});

it('fires a schedule in its own timezone', function () {
    [$workspace] = hardeningWorkspace();
    $this->travelTo(now('UTC')->setDate(2026, 10, 19)->setTime(3, 30));

    // 09:00 in Kolkata is 03:30 UTC.
    $kolkata = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Schedule, 'config' => ['cron' => '0 9 * * *', 'timezone' => 'Asia/Kolkata']]);
    $utc = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Schedule, 'config' => ['cron' => '0 9 * * *']]);

    $this->artisan('triggers:run-due')->assertSuccessful();

    expect($kolkata->events()->count())->toBe(1)
        ->and($utc->events()->count())->toBe(0);
});

it('rejects an unknown timezone through the API', function () {
    [$workspace, $owner] = hardeningWorkspace();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers", [
        'target_type' => 'workflow',
        'target_id' => $workflow->id,
        'type' => 'schedule',
        'config' => ['cron' => '0 9 * * *', 'timezone' => 'Mars/Olympus'],
    ])->assertUnprocessable()->assertJsonValidationErrors('config.timezone');
});

it('notifies the owner once when the circuit breaker switches a trigger off', function () {
    Notification::fake();
    config(['triggers.failures_before_disable' => 2]);
    [$workspace, $owner] = hardeningWorkspace();
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create(['created_by' => $owner->id]);

    $trigger->recordFailure();
    Notification::assertNothingSent();

    $trigger->recordFailure();
    $trigger->recordFailure();

    Notification::assertSentToTimes($owner, TriggerDisabledNotification::class, 1);
});

it('clears the failure streak when a trigger is switched back on', function () {
    [$workspace] = hardeningWorkspace();
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->inactive()->create(['consecutive_failure_count' => 5]);

    app(TriggerService::class)->update($trigger, ['is_active' => true]);

    expect($trigger->fresh()->consecutive_failure_count)->toBe(0);
});

it('prunes old events, but only compacts a polling trigger\'s', function () {
    config(['triggers.events_retention_days' => 30]);
    [$workspace, $owner] = hardeningWorkspace();
    $webhook = Trigger::factory()->webhook()->forWorkspace($workspace)->create();
    $polling = pollingTriggerIn($workspace, $owner);

    $old = fn (Trigger $trigger, string $deliveryId) => tap($trigger->events()->create([
        'source' => $trigger->type,
        'status' => TriggerEventStatus::Fired,
        'payload' => ['id' => $deliveryId],
        'payload_snippet' => '{"id":1}',
        'delivery_id' => $deliveryId,
    ]))->forceFill(['created_at' => now()->subDays(31)])->save();

    $old($webhook, 'w-old');
    $old($polling, 'p-old');
    $webhook->events()->create(['source' => TriggerType::Webhook, 'status' => TriggerEventStatus::Fired, 'delivery_id' => 'w-new']);

    $this->artisan('triggers:prune-events')->assertSuccessful();

    $compacted = $polling->events()->first();

    expect($webhook->events()->pluck('delivery_id')->all())->toBe(['w-new'])
        ->and($compacted->delivery_id)->toBe('p-old')
        ->and($compacted->payload)->toBeNull()
        ->and($compacted->payload_snippet)->toBeNull();
});
