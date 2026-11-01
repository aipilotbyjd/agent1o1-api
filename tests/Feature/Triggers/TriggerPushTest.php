<?php

use App\Enums\Triggers\ProviderEventStatus;
use App\Enums\Triggers\SubscriptionStatus;
use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderEvent;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerSubscription;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerStatus;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

/**
 * A published workflow whose Slack trigger node reads #C1 through a connected
 * account, with Slack push switched on.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $credentialData
 * @return array{0: Workspace, 1: User, 2: Trigger, 3: ConnectorCredential}
 */
function pushSlackTrigger(array $nodeConfig = [], array $credentialData = ['access_token' => 'xoxb-test', 'team_id' => 'T1'], bool $appInChannel = true): array
{
    config(['triggers.push.slack.enabled' => true, 'triggers.push.slack.signing_secret' => 'sekret']);
    Queue::fake([FireTriggerEvent::class]);
    // Slack is asked whether the app is in the channel when a trigger is published.
    Http::fake(['https://slack.com/api/conversations.info*' => Http::response(['ok' => true, 'channel' => ['name' => $appInChannel ? 'general' : 'sales', 'is_member' => $appInChannel]])]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'slack') ?? Connector::factory()->create(['key' => 'slack']))
        ->create(['data' => $credentialData, 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => 'slack_get_channel_history', 'config' => ['channel' => 'C1', 'credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
    SyncTriggerSubscription::dispatchSync($trigger->id);

    return [$workspace, $owner, $trigger->fresh(), $credential];
}

/**
 * Deliver a Slack event the way Slack does: signed.
 *
 * @param  array<string, mixed>  $body
 */
function deliverSlack(array $body, ?string $secret = 'sekret', ?int $timestamp = null): TestResponse
{
    $json = json_encode($body);
    $timestamp ??= time();
    $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$json}", (string) $secret);

    return test()->call('POST', '/api/hooks/apps/slack', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SLACK_SIGNATURE' => $signature,
    ], $json);
}

/**
 * @param  array<string, mixed>  $event
 * @return array<string, mixed>
 */
function slackCallback(array $event, string $eventId = 'Ev1'): array
{
    return ['type' => 'event_callback', 'event_id' => $eventId, 'team_id' => 'T1', 'event' => ['type' => 'message', 'channel' => 'C1', 'user' => 'U1', 'text' => 'hi', 'ts' => '100.1', ...$event]];
}

describe('Slack events ingress', function () {
    it('is not there while Slack push is off', function () {
        config(['triggers.push.slack.enabled' => false, 'triggers.push.slack.signing_secret' => 'sekret']);

        deliverSlack(['type' => 'url_verification', 'challenge' => 'abc'])->assertNotFound();
    });

    it('answers the URL check', function () {
        pushSlackTrigger();

        deliverSlack(['type' => 'url_verification', 'challenge' => 'abc'])->assertOk()->assertExactJson(['challenge' => 'abc']);
    });

    it('refuses a request signed with another secret, or too long ago', function () {
        pushSlackTrigger();

        deliverSlack(slackCallback([]), 'wrong')->assertUnauthorized();
        deliverSlack(slackCallback([]), 'sekret', time() - 3600)->assertUnauthorized();
    });

    it('starts the trigger for a new message and keeps the message id as its delivery id', function () {
        [, , $trigger] = pushSlackTrigger();

        deliverSlack(slackCallback([]))->assertOk();

        $event = $trigger->events()->firstOrFail();

        expect($event->status)->toBe(TriggerEventStatus::Queued)
            ->and($event->delivery_id)->toBe('100.1')
            ->and($event->payload['text'])->toBe('hi')
            ->and(ProviderEvent::query()->firstOrFail()->status)->toBe(ProviderEventStatus::Routed)
            ->and($trigger->subscription->fresh()->last_event_at)->not->toBeNull();
    });

    it('stores a redelivered event once and runs it once', function () {
        [, , $trigger] = pushSlackTrigger();

        deliverSlack(slackCallback([]))->assertOk();
        deliverSlack(slackCallback([]))->assertOk();

        expect(ProviderEvent::query()->count())->toBe(1)
            ->and($trigger->events()->count())->toBe(1);
    });

    it('does not repeat an item the poll already saw (same delivery id)', function () {
        [, , $trigger] = pushSlackTrigger();
        $trigger->events()->create(['source' => 'polling', 'status' => TriggerEventStatus::Ignored, 'delivery_id' => '100.1']);

        deliverSlack(slackCallback([]))->assertOk();

        expect($trigger->events()->count())->toBe(1);
    });

    it('ignores other channels, bots and channel events, and replies when asked', function () {
        [, , $trigger] = pushSlackTrigger(['ignore_replies' => true]);

        deliverSlack(slackCallback(['channel' => 'C2', 'ts' => '1.1'], 'Ev2'));
        deliverSlack(slackCallback(['bot_id' => 'B1', 'ts' => '1.2'], 'Ev3'));
        deliverSlack(slackCallback(['subtype' => 'channel_join', 'ts' => '1.3'], 'Ev4'));
        deliverSlack(slackCallback(['thread_ts' => '1.0', 'ts' => '1.4'], 'Ev5'));

        expect($trigger->events()->count())->toBe(0)
            ->and(ProviderEvent::query()->where('status', ProviderEventStatus::Dropped)->count())->toBe(4);
    });

    it('does not route to a trigger in another Slack team', function () {
        [, , $trigger] = pushSlackTrigger();

        deliverSlack([...slackCallback([]), 'team_id' => 'T2'])->assertOk();

        expect($trigger->events()->count())->toBe(0);
    });

    it('does not route to a switched-off trigger', function () {
        [, , $trigger] = pushSlackTrigger();
        $trigger->forceFill(['is_active' => false])->save();

        deliverSlack(slackCallback([]))->assertOk();

        expect($trigger->events()->count())->toBe(0);
    });
});

describe('subscriptions', function () {
    it('are made when a Slack trigger is published and removed when it is switched off', function () {
        [, , $trigger] = pushSlackTrigger();

        expect($trigger->subscription)->not->toBeNull()
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($trigger->subscription->routing_key)->toBe('T1');

        $trigger->forceFill(['is_active' => false])->save();
        SyncTriggerSubscription::dispatchSync($trigger->id);

        expect(TriggerSubscription::query()->where('trigger_id', $trigger->id)->exists())->toBeFalse();
    });

    it('learn the team from Slack when the account does not know it yet', function () {
        Http::fake(['https://slack.com/api/auth.test' => Http::response(['ok' => true, 'team_id' => 'T9'])]);

        [, , $trigger, $credential] = pushSlackTrigger(credentialData: ['access_token' => 'xoxb-test']);

        expect($trigger->subscription->routing_key)->toBe('T9')
            ->and($credential->fresh()->data['team_id'])->toBe('T9');
    });

    it('ask for a reconnect when Slack rejects the account', function () {
        Http::fake(['https://slack.com/api/auth.test' => Http::response(['ok' => false, 'error' => 'invalid_auth'])]);

        [, , $trigger] = pushSlackTrigger(credentialData: ['access_token' => 'xoxb-test']);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::ReconnectNeeded)
            ->and(app(TriggerStatus::class)->for($trigger->fresh())['action'])->toBe('reconnect');
    });

    it('are not made when Slack push is off', function () {
        [, , $trigger] = pushSlackTrigger();
        config(['triggers.push.slack.enabled' => false]);
        SyncTriggerSubscription::dispatchSync($trigger->id);

        expect(TriggerSubscription::query()->where('trigger_id', $trigger->id)->exists())->toBeFalse();
    });

    it('slow the poll down to a reconciliation check while push works', function () {
        [, , $trigger] = pushSlackTrigger();

        expect($trigger->pollIntervalMinutes())->toBe((int) config('triggers.push_reconcile_poll_minutes'));

        TriggerSubscription::query()->where('trigger_id', $trigger->id)->update(['status' => SubscriptionStatus::ReconnectNeeded]);

        expect($trigger->fresh()->pollIntervalMinutes())->toBe(1);
    });
});

describe('access warning', function () {
    it('tells the owner to add the app to the channel', function () {
        [, , $trigger] = pushSlackTrigger(appInChannel: false);

        $status = app(TriggerStatus::class)->for($trigger->fresh());

        expect($status['state'])->toBe('listening')
            ->and($status['hint'])->toContain('Add the app to #sales');
    });
});

describe('scheduling polls', function () {
    it('only polls triggers that are due and sets when they are next due', function () {
        [, , $due] = pushSlackTrigger();
        $due->forceFill(['next_poll_at' => now()->subMinute()])->save();
        [, , $later] = pushSlackTrigger();
        $later->forceFill(['next_poll_at' => now()->addHour()])->save();
        Http::fake(['https://slack.com/api/*' => Http::response(['ok' => true, 'messages' => []])]);

        $this->artisan('triggers:run-due')->assertSuccessful();

        expect($due->fresh()->next_poll_at->isFuture())->toBeTrue()
            ->and($later->fresh()->next_poll_at->gt(now()->addMinutes(59)))->toBeTrue();
    });
});

describe('trigger status', function () {
    it('says instant and listening for a working Slack subscription', function () {
        [, , $trigger] = pushSlackTrigger();

        $status = app(TriggerStatus::class)->for($trigger);

        expect($status['delivery']['mode'])->toBe('instant')
            ->and($status['state'])->toBe('listening');
    });

    it('says how often it checks without push', function () {
        [, , $trigger] = pushSlackTrigger();
        config(['triggers.push.slack.enabled' => false]);
        TriggerSubscription::query()->delete();

        $status = app(TriggerStatus::class)->for($trigger->fresh());

        expect($status['delivery'])->toBe(['mode' => 'polling', 'label' => 'Checks every 1 min'])
            ->and($status['state'])->toBe('checking');
    });

    it('says off after failures with a way to resume', function () {
        [, , $trigger] = pushSlackTrigger();
        $trigger->forceFill(['is_active' => false, 'consecutive_failure_count' => 3])->save();

        $status = app(TriggerStatus::class)->for($trigger->fresh());

        expect($status['state'])->toBe('off_failures')
            ->and($status['action'])->toBe('resume');
    });

    it('is part of the trigger API', function () {
        [$workspace, $owner, $trigger] = pushSlackTrigger();
        Passport::actingAs($owner);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers")
            ->assertOk()
            ->assertJsonPath('data.triggers.0.status.state', 'listening')
            ->assertJsonPath('data.triggers.0.status.delivery.mode', 'instant');
    });
});

describe('sample and replay', function () {
    it('shows the newest items without recording anything', function () {
        [$workspace, $owner, $trigger] = pushSlackTrigger();
        Passport::actingAs($owner);
        Http::fake(['https://slack.com/api/*' => Http::response(['ok' => true, 'messages' => [
            ['ts' => '3.0', 'text' => 'c'], ['ts' => '2.0', 'text' => 'b'], ['ts' => '1.0', 'text' => 'a'],
        ]])]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/sample")->assertOk();

        expect(array_column($response->json('data.items'), 'id'))->toBe(['3.0', '2.0', '1.0'])
            ->and($trigger->events()->count())->toBe(0);
    });

    it('explains when a trigger has nothing to sample', function () {
        [$workspace, $owner] = pushSlackTrigger();
        $manual = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Manual]);
        Passport::actingAs($owner);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers/{$manual->id}/sample")->assertUnprocessable();
    });

    it('runs a past event again as a new event', function () {
        [$workspace, $owner, $trigger] = pushSlackTrigger();
        Passport::actingAs($owner);
        $past = $trigger->events()->create(['source' => 'polling', 'status' => TriggerEventStatus::Fired, 'delivery_id' => 'x', 'payload' => ['ts' => '9.9']]);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/events/{$past->id}/replay")->assertStatus(202);

        expect($trigger->events()->count())->toBe(2)
            ->and($trigger->events()->where('id', '!=', $past->id)->first()->payload)->toBe(['ts' => '9.9']);
    });

    it('will not replay an event whose payload was cleared', function () {
        [$workspace, $owner, $trigger] = pushSlackTrigger();
        Passport::actingAs($owner);
        $past = $trigger->events()->create(['source' => 'polling', 'status' => TriggerEventStatus::Fired, 'delivery_id' => 'y', 'payload' => null]);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/triggers/{$trigger->id}/events/{$past->id}/replay")->assertUnprocessable();
    });
});
