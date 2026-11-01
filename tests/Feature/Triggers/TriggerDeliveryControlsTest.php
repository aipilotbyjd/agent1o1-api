<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Billing\Plan;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderEvent;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerSampler;
use App\Services\Triggers\TriggerService;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A published Slack reader trigger with the given node settings.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @return array{0: Workspace, 1: User, 2: Trigger}
 */
function controlSlackTrigger(string $nodeType = 'slack_get_channel_history', array $nodeConfig = []): array
{
    Queue::fake([FireTriggerEvent::class]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'slack') ?? Connector::factory()->create(['key' => 'slack']))
        ->create(['data' => ['access_token' => 'xoxb-test', 'team_id' => 'T1'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['channel' => 'C1', 'credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    return [$workspace, $owner, Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail()];
}

describe('start from', function () {
    it('skips what is already there by default', function () {
        [, , $trigger] = controlSlackTrigger();
        Http::fake(['https://slack.com/api/*' => Http::response(['ok' => true, 'messages' => [['ts' => '1.0', 'text' => 'old']]])]);

        dispatch_sync(new PollTrigger($trigger));

        expect($trigger->events()->where('status', TriggerEventStatus::Queued)->count())->toBe(0);
    });

    it('runs what is already there when told to start from the beginning', function () {
        [, , $trigger] = controlSlackTrigger(nodeConfig: ['start_from' => 'beginning']);
        Http::fake(['https://slack.com/api/*' => Http::response(['ok' => true, 'messages' => [['ts' => '1.0', 'text' => 'old']]])]);

        expect($trigger->config['start_from'])->toBe('beginning');

        dispatch_sync(new PollTrigger($trigger));

        expect($trigger->events()->where('status', TriggerEventStatus::Queued)->count())->toBe(1);
    });
});

describe('runs per minute', function () {
    it('lets events past up to the limit and holds the rest for the next minute', function () {
        [, , $trigger] = controlSlackTrigger(nodeConfig: ['max_runs_per_minute' => 1]);
        $first = $trigger->events()->create(['source' => TriggerType::Polling, 'status' => TriggerEventStatus::Queued, 'payload' => ['a' => 1], 'delivery_id' => 'a']);
        $second = $trigger->events()->create(['source' => TriggerType::Polling, 'status' => TriggerEventStatus::Queued, 'payload' => ['a' => 2], 'delivery_id' => 'b']);

        expect($trigger->config['max_runs_per_minute'])->toBe(1);

        (new FireTriggerEvent($trigger, $first))->handle(app(TriggerService::class));
        (new FireTriggerEvent($trigger, $second))->handle(app(TriggerService::class));

        expect($first->fresh()->status)->toBe(TriggerEventStatus::Fired)
            ->and($second->fresh()->status)->toBe(TriggerEventStatus::Queued);

        RateLimiter::clear("trigger-rate:{$trigger->id}");
        (new FireTriggerEvent($trigger, $second->fresh()))->handle(app(TriggerService::class));

        expect($second->fresh()->status)->toBe(TriggerEventStatus::Fired);
    });
});

describe('grouping events', function () {
    it('holds events instead of running them, then runs them as one', function () {
        [, , $trigger] = controlSlackTrigger(nodeConfig: ['batch_seconds' => 30]);
        $service = app(TriggerService::class);

        $a = $service->receive($trigger, TriggerType::Polling, ['n' => 1], 'a');
        $b = $service->receive($trigger, TriggerType::Polling, ['n' => 2], 'b');

        expect($a->status)->toBe(TriggerEventStatus::Held)
            ->and($b->status)->toBe(TriggerEventStatus::Held);
        Queue::assertNothingPushed();

        $combined = $service->flushHeld($trigger);

        expect($combined->payload['count'])->toBe(2)
            ->and($combined->payload['items'])->toBe([['n' => 1], ['n' => 2]])
            ->and($a->fresh()->status)->toBe(TriggerEventStatus::Batched)
            ->and($combined->status)->toBe(TriggerEventStatus::Queued);
        Queue::assertPushed(FireTriggerEvent::class, 1);
    });

    it('flushes only when the window has passed', function () {
        [, , $trigger] = controlSlackTrigger(nodeConfig: ['batch_seconds' => 60]);
        app(TriggerService::class)->receive($trigger, TriggerType::Polling, ['n' => 1], 'a');

        $this->artisan('triggers:flush-batches')->assertSuccessful();
        expect($trigger->events()->where('status', TriggerEventStatus::Held)->count())->toBe(1);

        $this->travel(61)->seconds();
        $this->artisan('triggers:flush-batches')->assertSuccessful();

        expect($trigger->events()->where('status', TriggerEventStatus::Held)->count())->toBe(0)
            ->and($trigger->events()->where('status', TriggerEventStatus::Queued)->count())->toBe(1);
    });

    it('never holds a Run now', function () {
        [, , $trigger] = controlSlackTrigger(nodeConfig: ['batch_seconds' => 60]);

        $event = app(TriggerService::class)->receive($trigger, TriggerType::Manual, ['n' => 1]);

        expect($event->status)->toBe(TriggerEventStatus::Queued);
    });
});

describe('plan limits', function () {
    it('lets a plan set how often app triggers may check', function () {
        expect((new Plan(['limits' => ['min_poll_interval_minutes' => 15]]))->minPollIntervalMinutes())->toBe(15)
            ->and((new Plan(['limits' => []]))->minPollIntervalMinutes())->toBe(1);
    });
});

describe('samples', function () {
    it('shows a webhook what it last received', function () {
        [$workspace] = controlSlackTrigger();
        $webhook = Trigger::factory()->webhook()->forWorkspace($workspace)->create();
        $webhook->events()->create(['source' => TriggerType::Webhook, 'status' => TriggerEventStatus::Fired, 'payload' => ['order' => 7], 'delivery_id' => 'o-7']);
        $webhook->events()->create(['source' => TriggerType::Webhook, 'status' => TriggerEventStatus::Rejected, 'payload' => null]);

        $items = app(TriggerSampler::class)->sample($webhook);

        expect($items)->toBe([['id' => 'o-7', 'payload' => ['order' => 7]]]);
    });

    it('shows a schedule when it will next fire', function () {
        [$workspace] = controlSlackTrigger();
        $schedule = Trigger::factory()->schedule('0 9 * * *')->forWorkspace($workspace)->create();

        $items = app(TriggerSampler::class)->sample($schedule);

        expect($items)->toHaveCount(1)
            ->and($items[0]['payload'])->toHaveKey('scheduled_at');
    });
});

describe('Slack reactions', function () {
    it('lists one item per person and emoji, narrowed to the emoji you chose', function () {
        $node = app(NodeRegistry::class)->resolve('slack_reaction_added');
        $output = ['reactions' => [
            ['ts' => '1.0', 'emoji' => 'white_check_mark', 'user' => 'U1'],
            ['ts' => '1.0', 'emoji' => 'eyes', 'user' => 'U2'],
        ]];

        expect($node->triggerItems($output, []))->toHaveCount(2)
            ->and($node->triggerItems($output, ['emoji' => [':white_check_mark:']]))->toHaveCount(1)
            ->and($node->triggerItemId($output['reactions'][0]))->toBe('1.0:white_check_mark:U1');
    });

    it('reads reactions from recent messages when polling', function () {
        [, , $trigger] = controlSlackTrigger('slack_reaction_added', ['emoji' => ['eyes']]);
        Http::fake(['https://slack.com/api/*' => Http::response(['ok' => true, 'messages' => [
            ['ts' => '2.0', 'text' => 'b', 'reactions' => [['name' => 'eyes', 'users' => ['U2']], ['name' => 'tada', 'users' => ['U3']]]],
        ]])]);

        $items = app(TriggerSampler::class)->sample($trigger);

        expect($items)->toHaveCount(1)
            ->and($items[0]['id'])->toBe('2.0:eyes:U2');
    });

    it('starts a reaction trigger for a reaction event and a message trigger only for messages', function () {
        config(['triggers.push.slack.enabled' => true, 'triggers.push.slack.signing_secret' => 'sekret']);
        Http::fake(['https://slack.com/api/conversations.info' => Http::response(['ok' => true, 'channel' => ['name' => 'general', 'is_member' => true]])]);
        [$workspace, $owner, $reaction] = controlSlackTrigger('slack_reaction_added', ['emoji' => ['eyes']]);
        SyncTriggerSubscription::dispatchSync($reaction->id);

        $event = [
            'type' => 'event_callback', 'event_id' => 'Ev9', 'team_id' => 'T1',
            'event' => ['type' => 'reaction_added', 'user' => 'U2', 'reaction' => 'eyes', 'item' => ['type' => 'message', 'channel' => 'C1', 'ts' => '5.5'], 'event_ts' => '6.6'],
        ];
        $json = json_encode($event);
        $timestamp = time();

        $this->call('POST', '/api/hooks/apps/slack', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SLACK_SIGNATURE' => 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$json}", 'sekret'),
        ], $json)->assertOk();

        expect($reaction->events()->sole()->delivery_id)->toBe('5.5:eyes:U2')
            ->and(ProviderEvent::query()->count())->toBe(1);
    });
});
