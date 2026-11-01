<?php

use App\Contracts\Triggers\RunStarter;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\WorkflowValidationException;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Billing\Plan;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Notifications\Triggers\TriggerDisabledNotification;
use App\Services\Billing\CreditGate;
use App\Services\Triggers\TargetRunStarter;
use App\Services\Triggers\TriggerService;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

use function Pest\Laravel\mock;

/**
 * @return array{0: Workspace, 1: User, 2: Workflow}
 */
function productionWorkflow(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner, Workflow::factory()->forWorkspace($workspace)->create()];
}

/**
 * @param  array<string, mixed>  $config
 */
function publishSingleTrigger(Workflow $workflow, User $owner, string $type, array $config): Trigger
{
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $type, 'config' => $config],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    return Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
}

describe('Activate as flow trigger', function () {
    it('creates the trigger switched off when the switch is off', function () {
        [, $owner, $workflow] = productionWorkflow();

        $trigger = publishSingleTrigger($workflow, $owner, 'webhook_trigger', ['activate' => false]);

        expect($trigger->is_active)->toBeFalse();
    });

    it('keeps a breaker-switched-off trigger off across a republish that didn\'t touch the switch', function () {
        [, $owner, $workflow] = productionWorkflow();
        $trigger = publishSingleTrigger($workflow, $owner, 'webhook_trigger', []);
        $trigger->forceFill(['is_active' => false, 'consecutive_failure_count' => 3])->save();

        $republished = publishSingleTrigger($workflow, $owner, 'webhook_trigger', ['filters' => [['path' => 'a', 'value' => 'b']]]);

        expect($republished->is_active)->toBeFalse();
    });

    it('switches a trigger back on when the author turns the switch on, clearing the streak', function () {
        [, $owner, $workflow] = productionWorkflow();
        publishSingleTrigger($workflow, $owner, 'webhook_trigger', ['activate' => false]);

        $trigger = publishSingleTrigger($workflow, $owner, 'webhook_trigger', ['activate' => true]);

        expect($trigger->is_active)->toBeTrue()
            ->and($trigger->consecutive_failure_count)->toBe(0);
    });
});

describe('schedules', function () {
    it('stores when a schedule is next due', function () {
        [$workspace] = productionWorkflow();
        $this->travelTo(now('UTC')->setDate(2026, 10, 19)->setTime(8, 10));

        $trigger = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Schedule, 'config' => ['cron' => '0 9 * * *']]);

        expect($trigger->next_run_at->toDateTimeString())->toBe('2026-10-19 09:00:00');
    });

    it('reads a schedule with no timezone of its own in the workspace timezone', function () {
        [$workspace] = productionWorkflow();
        $workspace->update(['timezone' => 'Asia/Kolkata']);
        $this->travelTo(now('UTC')->setDate(2026, 10, 19)->setTime(2, 0));

        $trigger = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Schedule, 'config' => ['cron' => '0 9 * * *']]);

        expect($trigger->next_run_at->toDateTimeString())->toBe('2026-10-19 03:30:00');
    });

    it('prefers the schedule\'s own timezone over the workspace\'s', function () {
        [$workspace] = productionWorkflow();
        $workspace->update(['timezone' => 'Asia/Kolkata']);
        $this->travelTo(now('UTC')->setDate(2026, 10, 19)->setTime(2, 0));

        $trigger = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Schedule, 'config' => ['cron' => '0 9 * * *', 'timezone' => 'UTC']]);

        expect($trigger->next_run_at->toDateTimeString())->toBe('2026-10-19 09:00:00');
    });

    it('moves schedules that use the workspace timezone when it changes', function () {
        [$workspace] = productionWorkflow();
        $this->travelTo(now('UTC')->setDate(2026, 10, 19)->setTime(2, 0));
        $inherits = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Schedule, 'config' => ['cron' => '0 9 * * *']]);
        $pinned = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Schedule, 'config' => ['cron' => '0 9 * * *', 'timezone' => 'UTC']]);

        app(WorkspaceService::class)->update($workspace, ['timezone' => 'Asia/Kolkata']);

        expect($inherits->fresh()->next_run_at->toDateTimeString())->toBe('2026-10-19 03:30:00')
            ->and($pinned->fresh()->next_run_at->toDateTimeString())->toBe('2026-10-19 09:00:00');
    });

    it('saves the workspace timezone through the API, refusing unknown ones', function () {
        [$workspace, $owner] = productionWorkflow();
        Passport::actingAs($owner);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}", ['timezone' => 'Mars/Olympus'])
            ->assertJsonValidationErrors('timezone');

        $this->patchJson("/api/v1/workspaces/{$workspace->id}", ['timezone' => 'Asia/Kolkata'])
            ->assertSuccessful()
            ->assertJsonPath('data.workspace.timezone', 'Asia/Kolkata');
    });

    it('catches up a slot missed while the scheduler was down, once', function () {
        [$workspace] = productionWorkflow();
        $trigger = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Schedule, 'config' => ['cron' => '* * * * *']]);
        $trigger->forceFill(['next_run_at' => now()->subMinutes(5)->startOfMinute()])->save();

        $this->artisan('triggers:run-due')->assertSuccessful();
        $this->artisan('triggers:run-due')->assertSuccessful();

        expect($trigger->events()->count())->toBe(1)
            ->and($trigger->fresh()->next_run_at->isFuture())->toBeTrue();
    });

    it('skips a slot missed beyond the catch-up window', function () {
        config(['triggers.schedule_catch_up_minutes' => 10]);
        [$workspace] = productionWorkflow();
        $trigger = Trigger::factory()->forWorkspace($workspace)->create(['type' => TriggerType::Schedule, 'config' => ['cron' => '0 9 * * *']]);
        $trigger->forceFill(['next_run_at' => now()->subHours(3)])->save();

        $this->artisan('triggers:run-due')->assertSuccessful();

        expect($trigger->events()->count())->toBe(0)
            ->and($trigger->fresh()->next_run_at->isFuture())->toBeTrue();
    });
});

describe('breaker, credits and overlap', function () {
    it('honours a trigger\'s own failure limit', function () {
        [$workspace] = productionWorkflow();
        $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create(['config' => ['max_failures' => 1]]);

        $trigger->recordFailure();

        expect($trigger->fresh()->is_active)->toBeFalse();
    });

    it('drops events when the workspace is out of credits, without counting a failure', function () {
        Queue::fake([FireTriggerEvent::class]);
        mock(CreditGate::class)->shouldReceive('assertCanStartRun')->andThrow(new InsufficientCreditsException(1, 0));
        [$workspace, , $workflow] = productionWorkflow();
        $workflow = Workflow::factory()->forWorkspace($workspace)->published()->create();
        $trigger = Trigger::factory()->webhook()->forWorkflow($workflow)->create();

        $event = app(TriggerService::class)->receive($trigger, TriggerType::Webhook, ['a' => 1], 'd-1');

        expect($event->status)->toBe(TriggerEventStatus::Skipped)
            ->and($trigger->fresh()->consecutive_failure_count)->toBe(0)
            ->and($trigger->fresh()->consecutive_skip_count)->toBe(1);
        Queue::assertNothingPushed();
    });

    it('switches off after too many drops in a row, saying why', function () {
        Notification::fake();
        config(['triggers.skips_before_disable' => 2]);
        [$workspace, $owner] = productionWorkflow();
        $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create(['created_by' => $owner->id]);

        $trigger->recordSkip();
        $trigger->recordSkip();

        expect($trigger->fresh()->is_active)->toBeFalse();
        Notification::assertSentTo($owner, TriggerDisabledNotification::class, fn (TriggerDisabledNotification $notification) => $notification->data['reason'] === 'skips');
    });

    it('skips an event while the last run is going when set to skip', function () {
        Queue::fake([FireTriggerEvent::class]);
        app()->instance(RunStarter::class, new class implements RunStarter
        {
            public function canRun(Trigger $trigger): bool
            {
                return true;
            }

            public function isAlreadyRunning(Trigger $trigger): bool
            {
                return true;
            }

            public function activeRuns(Trigger $trigger): int
            {
                return 1;
            }

            public function start(Trigger $trigger, array $payload): string
            {
                return 'never';
            }
        });
        [$workspace] = productionWorkflow();
        $skips = Trigger::factory()->webhook()->forWorkspace($workspace)->create(['config' => ['if_already_running' => 'skip']]);
        $queues = Trigger::factory()->webhook()->forWorkspace($workspace)->create();

        $triggers = app(TriggerService::class);

        expect($triggers->receive($skips, TriggerType::Webhook, [], 'd-1')->status)->toBe(TriggerEventStatus::Skipped)
            ->and($triggers->receive($queues, TriggerType::Webhook, [], 'd-1')->status)->toBe(TriggerEventStatus::Queued);
    });
});

describe('plan limit', function () {
    it('refuses to switch on more triggers than the plan allows', function () {
        Plan::factory()->create(['slug' => 'free', 'limits' => ['active_triggers' => 1]]);
        config(['billing.default_plan' => 'free']);
        [, $owner, $workflow] = productionWorkflow();

        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'one', 'type' => 'webhook_trigger', 'config' => []],
                ['key' => 'two', 'type' => 'webhook_trigger', 'config' => []],
            ],
            'edges' => [],
        ]);

        expect(fn () => $workflow->fresh()->publishVersion(publisher: $owner))->toThrow(PlanLimitExceededException::class);
        expect(Trigger::query()->where('target_id', $workflow->id)->count())->toBe(0);
    });

    it('lets switched-off triggers past the limit', function () {
        Plan::factory()->create(['slug' => 'free', 'limits' => ['active_triggers' => 1]]);
        config(['billing.default_plan' => 'free']);
        [, $owner, $workflow] = productionWorkflow();

        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'one', 'type' => 'webhook_trigger', 'config' => []],
                ['key' => 'two', 'type' => 'webhook_trigger', 'config' => ['activate' => false]],
            ],
            'edges' => [],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);

        expect(Trigger::query()->where('target_id', $workflow->id)->count())->toBe(2);
    });
});

describe('app triggers', function () {
    beforeEach(function () {
        Queue::fake([FireTriggerEvent::class]);
    });

    it('offers trigger mode on reader nodes only', function () {
        $catalog = collect(app(NodeRegistry::class)->catalog())->keyBy('type');

        expect($catalog['slack_get_channel_history']['supports_trigger'])->toBeTrue()
            ->and($catalog['slack_get_channel_history']['config_schema']['properties'])->toHaveKeys(['activate', 'poll_interval_minutes'])
            ->and($catalog['slack_post_message']['supports_trigger'])->toBeFalse();
    });

    it('publishes nothing for a reader node whose switch is off', function () {
        [, $owner, $workflow] = productionWorkflow();

        $workflow->replaceGraph(['nodes' => [['key' => 'start', 'type' => 'slack_get_channel_history', 'config' => ['channel' => 'C1', 'access_token' => 'xoxb-test']]], 'edges' => []]);
        $workflow->fresh()->publishVersion(publisher: $owner);

        expect(Trigger::query()->where('target_id', $workflow->id)->count())->toBe(0);
    });

    it('polls a switched-on Slack reader: baseline first, then one event per new message', function () {
        [, $owner, $workflow] = productionWorkflow();
        $trigger = publishSingleTrigger($workflow, $owner, 'slack_get_channel_history', ['channel' => 'C1', 'access_token' => 'xoxb-test', 'activate' => true]);

        expect($trigger->type)->toBe(TriggerType::Polling)
            ->and($trigger->config['node_type'])->toBe('slack_get_channel_history')
            ->and($trigger->config['poll_interval_minutes'])->toBe(1)
            ->and($trigger->config['node_config'])->not->toHaveKey('activate');

        Http::fakeSequence('https://slack.com/api/*')
            ->push(['ok' => true, 'messages' => [['ts' => '1.0', 'text' => 'old']]])
            ->push(['ok' => true, 'messages' => [['ts' => '2.0', 'text' => 'new'], ['ts' => '1.0', 'text' => 'old']]]);

        dispatch_sync(new PollTrigger($trigger));
        dispatch_sync(new PollTrigger($trigger->fresh()));

        $queued = $trigger->events()->where('status', TriggerEventStatus::Queued)->get();

        expect($queued)->toHaveCount(1)
            ->and($queued->first()->delivery_id)->toBe('2.0')
            ->and($queued->first()->payload)->toBe(['ts' => '2.0', 'text' => 'new']);
    });

    it('skips bot messages and thread replies in a Slack trigger unless told otherwise', function () {
        $node = app(NodeRegistry::class)->resolve('slack_get_channel_history');
        $output = ['messages' => [
            ['ts' => '1.0', 'text' => 'human'],
            ['ts' => '2.0', 'text' => 'bot', 'bot_id' => 'B1'],
            ['ts' => '3.0', 'text' => 'reply', 'thread_ts' => '1.0'],
            ['ts' => '4.0', 'text' => 'thread parent', 'thread_ts' => '4.0'],
        ]];

        expect(array_column($node->triggerItems($output, []), 'ts'))->toBe(['1.0', '3.0', '4.0'])
            ->and(array_column($node->triggerItems($output, ['ignore_replies' => true]), 'ts'))->toBe(['1.0', '4.0'])
            ->and(array_column($node->triggerItems($output, ['ignore_bot_messages' => false]), 'ts'))->toBe(['1.0', '2.0', '3.0', '4.0']);
    });

    it('outputs the item it fired for when it starts the run', function () {
        [, $owner, $workflow] = productionWorkflow();
        $trigger = publishSingleTrigger($workflow, $owner, 'slack_get_channel_history', ['channel' => 'C1', 'access_token' => 'xoxb-test', 'activate' => true]);
        $trigger->forceFill(['created_by' => $owner->id])->save();
        Http::fake();

        $runId = app(TargetRunStarter::class)->start($trigger, ['ts' => '2.0', 'text' => 'new']);
        $run = Run::with('nodeRuns')->findOrFail($runId);

        expect($run->status)->toBe(RunStatus::Completed)
            ->and($run->nodeRuns->firstWhere('key', 'start')->output)->toBe(['ts' => '2.0', 'text' => 'new'])
            ->and($run->nodeRuns->firstWhere('key', 'next')->status)->toBe(NodeRunStatus::Completed);
        Http::assertNothingSent();
    });

    it('refuses a connection into a reader switched on as a trigger', function () {
        [, , $workflow] = productionWorkflow();

        expect(fn () => $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'first', 'type' => 'transform', 'config' => ['mapping' => []]],
                ['key' => 'reader', 'type' => 'slack_get_channel_history', 'config' => ['channel' => 'C1', 'activate' => true]],
            ],
            'edges' => [['from' => 'first', 'to' => 'reader']],
        ]))->toThrow(WorkflowValidationException::class);
    });
});

it('refuses an oversized webhook body', function () {
    config(['triggers.max_webhook_body_kb' => 1]);
    [$workspace] = productionWorkflow();
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create();

    $this->postJson("/api/hooks/{$trigger->token}", ['blob' => str_repeat('x', 2048)])->assertStatus(413);

    expect($trigger->events()->count())->toBe(0);
});

it('reports 24-hour stats and the target name in the list', function () {
    [$workspace, $owner, $workflow] = productionWorkflow();
    $trigger = Trigger::factory()->webhook()->forWorkflow($workflow)->create();
    $trigger->events()->create(['source' => TriggerType::Webhook, 'status' => TriggerEventStatus::Fired, 'delivery_id' => 'a']);
    $trigger->events()->create(['source' => TriggerType::Webhook, 'status' => TriggerEventStatus::Failed, 'delivery_id' => 'b']);
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/triggers")
        ->assertSuccessful()
        ->assertJsonPath('data.triggers.0.stats_24h', ['fired' => 1, 'failed' => 1, 'skipped' => 0, 'rejected' => 0])
        ->assertJsonPath('data.triggers.0.target_name', $workflow->name);
});

describe('legacy triggers', function () {
    it('lists without changing anything by default', function () {
        [, , $workflow] = productionWorkflow();
        Trigger::factory()->webhook()->forWorkflow($workflow)->create();

        $this->artisan('triggers:legacy')->assertSuccessful();

        expect(Trigger::query()->whereNull('trigger_node_key')->count())->toBe(1);
    });

    it('converts a webhook into a node on the draft, keeping its URL', function () {
        [, , $workflow] = productionWorkflow();
        $workflow->replaceGraph(['nodes' => [['key' => 'step', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []]);
        $trigger = Trigger::factory()->webhook()->forWorkflow($workflow)->create();
        $token = $trigger->token;

        $this->artisan('triggers:legacy --convert')->assertSuccessful();

        $trigger->refresh();
        $graph = $workflow->fresh()->editorGraph();
        $node = collect($graph['nodes'])->firstWhere('key', $trigger->trigger_node_key);

        expect($node['type'])->toBe('webhook_trigger')
            ->and($trigger->token)->toBe($token)
            ->and($graph['edges'])->toContain(['from' => $trigger->trigger_node_key, 'to' => 'step', 'condition' => null]);
    });

    it('deletes a manual trigger rather than converting it', function () {
        [, , $workflow] = productionWorkflow();
        Trigger::factory()->forWorkflow($workflow)->create(['type' => TriggerType::Manual]);

        $this->artisan('triggers:legacy --convert')->assertSuccessful();

        expect(Trigger::query()->count())->toBe(0);
    });
});

describe('webhook authentication', function () {
    beforeEach(function () {
        Queue::fake([FireTriggerEvent::class]);
    });

    /**
     * @param  array<string, mixed>  $config
     */
    function authWebhook(array $config, ?string $secret = null): Trigger
    {
        [, , $workflow] = productionWorkflow();
        $workflow = Workflow::factory()->forWorkspace($workflow->workspace)->published()->create();

        $trigger = Trigger::factory()->webhook()->forWorkflow($workflow)->create(['config' => $config]);
        $trigger->forceFill(['signing_secret' => $secret])->save();

        return $trigger;
    }

    it('accepts anyone with the URL when set to none', function () {
        $trigger = authWebhook(['authentication' => 'none'], 'ignored-secret');

        $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1])->assertOk();
    });

    it('checks the token header, defaulting to X-Webhook-Token', function () {
        $trigger = authWebhook(['authentication' => 'header_token'], 's3cret');

        $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1])->assertUnauthorized();
        $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1], ['X-Webhook-Token' => 'wrong'])->assertUnauthorized();
        $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1], ['X-Webhook-Token' => 's3cret'])->assertOk();
    });

    it('names the token, not a signature, when a header token is rejected', function () {
        $trigger = authWebhook(['authentication' => 'header_token'], 's3cret');

        $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1], ['X-Webhook-Token' => 'wrong'])
            ->assertUnauthorized()
            ->assertSeeText('Invalid token.');

        expect($trigger->events()->first()->error)->toBe('Token verification failed.');
    });

    it('reads the token from a custom header', function () {
        $trigger = authWebhook(['authentication' => 'header_token', 'header_name' => 'X-Acme-Key'], 's3cret');

        $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1], ['X-Acme-Key' => 's3cret'])->assertOk();
    });

    it('rejects everything until a secret is set, rather than accepting anyone', function (string $authentication) {
        $trigger = authWebhook(['authentication' => $authentication]);

        $this->postJson("/api/hooks/{$trigger->token}", ['a' => 1], ['X-Webhook-Token' => ''])->assertUnauthorized();
    })->with(['header_token', 'signature']);

    it('checks an HMAC-SHA256 signature of the raw body', function () {
        $trigger = authWebhook(['authentication' => 'signature'], 's3cret');
        $body = json_encode(['a' => 1]);

        $this->call('POST', "/api/hooks/{$trigger->token}", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE_256' => 'sha256=bad'], $body)->assertUnauthorized();
        $this->call('POST', "/api/hooks/{$trigger->token}", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 's3cret')], $body)->assertOk();
    });
});
